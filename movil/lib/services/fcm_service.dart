import 'dart:async';

import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:alertas_ciudadanas/services/user_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

class FcmService {
  static const _notificationSound = RawResourceAndroidNotificationSound('alert_notification');
  static const _generalChannel = AndroidNotificationChannel(
    'app_notifications_v1', 'Notificaciones',
    importance: Importance.high,
    sound: _notificationSound,
  );
  static const _emergencySiren = AndroidNotificationChannel(
    'nearby_emergency_siren_v3', 'Sirena de emergencias cercanas',
    importance: Importance.high,
    sound: RawResourceAndroidNotificationSound('nearby_emergency_short'),
  );
  static const _emergencySilent = AndroidNotificationChannel(
    'nearby_emergency_silent_v1', 'Emergencias cercanas sin sonido',
    importance: Importance.high,
    playSound: false,
  );
  static const _ambulanceChannel = 'ambulance_alerts_sound_v3';
  static const _ambulanceAudio = MethodChannel('alertas/ambulance_audio');
  static const _ambulanceSound = RawResourceAndroidNotificationSound('ambulance_proximity_siren');
  StreamSubscription<String>? _tokenUpdates;
  StreamSubscription<RemoteMessage>? _foregroundMessages;
  StreamSubscription<RemoteMessage>? _openedMessages;
  bool _disposed = false;
  void Function(int)? _onAssignmentOpened;
  void Function(int)? _onCaseOpened;
  void Function(int)? _onReportOpened;
  void Function(int)? _onMissingCaseOpened;
  /// true si Firebase se inicializó (existe google-services.json)
  bool get _firebaseReady => Firebase.apps.isNotEmpty;

  FirebaseMessaging get _firebaseMessaging => FirebaseMessaging.instance;
  final UserService _userService = UserService();
  final FlutterLocalNotificationsPlugin _localNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  Future<void> init(String userId, {void Function(int)? onAssignmentOpened, void Function(int)? onCaseOpened, void Function(int)? onReportOpened, void Function(int)? onMissingCaseOpened, VoidCallback? onForegroundReport}) async {
    _onAssignmentOpened = onAssignmentOpened;
    _onCaseOpened = onCaseOpened;
    _onReportOpened = onReportOpened;
    _onMissingCaseOpened = onMissingCaseOpened;
    if (!_firebaseReady) {
      debugPrint('FCM omitido: Firebase no está configurado');
      return;
    }
    try {
      // Configurar notificaciones locales para mostrar el push real cuando la app está abierta
      const AndroidInitializationSettings initializationSettingsAndroid =
          AndroidInitializationSettings('@mipmap/ic_launcher');
      const InitializationSettings initializationSettings =
          InitializationSettings(
            android: initializationSettingsAndroid,
            iOS: DarwinInitializationSettings(
              requestAlertPermission: false,
              requestBadgePermission: false,
              requestSoundPermission: false,
            ),
          );
      await _localNotificationsPlugin.initialize(
        settings: initializationSettings,
        onDidReceiveNotificationResponse: (response) {
          _openPayload(response.payload);
        },
      );
      await _localNotificationsPlugin
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(const AndroidNotificationChannel(
            _ambulanceChannel, 'Avisos de ambulancias',
            description: 'Avisos cuando una ambulancia se aproxima a tu ubicación.',
            importance: Importance.high,
            playSound: true,
            sound: _ambulanceSound,
            audioAttributesUsage: AudioAttributesUsage.alarm,
          ));
      final androidNotifications = _localNotificationsPlugin
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      await androidNotifications?.createNotificationChannel(_emergencySiren);
      await androidNotifications?.createNotificationChannel(_emergencySilent);
      await androidNotifications?.createNotificationChannel(_generalChannel);
      await _firebaseMessaging.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );
      if (_disposed) return;
      if (onForegroundReport != null) {
        listenToForegroundMessages(onForegroundReport);
      }
      _openedMessages = FirebaseMessaging.onMessageOpenedApp.listen(_recordOpened);
      final initial = await _firebaseMessaging.getInitialMessage();
      if (initial != null && !_disposed) _recordOpened(initial);
      final launch = await _localNotificationsPlugin.getNotificationAppLaunchDetails();
      final localAlertId = launch?.notificationResponse?.payload;
      if (launch?.didNotificationLaunchApp == true && localAlertId != null && !_disposed) {
        _openPayload(localAlertId);
      }

      // Solicitar permisos al usuario (necesario en iOS y Android 13+)
      NotificationSettings settings = await _firebaseMessaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      if (_disposed) return;

      if (settings.authorizationStatus == AuthorizationStatus.authorized) {
        _tokenUpdates = _firebaseMessaging.onTokenRefresh.listen((newToken) async {
          try {
            await _userService.updateFcmToken(userId, newToken);
          } catch (error) {
            debugPrint('No se pudo renovar el registro FCM: ${error.runtimeType}');
          }
        });
        // Obtener el token del dispositivo
        String? token = await _firebaseMessaging.getToken();
        if (token != null && !_disposed) {
          // Enviar el token a nuestro backend
          await _userService.updateFcmToken(userId, token);
        }

        // Escuchar actualizaciones del token (si cambia, reenviar al backend)
      }
    } catch (e) {
      debugPrint('Error inicializando FCM: ${e.runtimeType}');
    }
  }

  // Escuchar mensajes en primer plano
  void listenToForegroundMessages(Function onMessageReceived) {
    if (!_firebaseReady || _disposed) return;
    _foregroundMessages = FirebaseMessaging.onMessage.listen((RemoteMessage message) async {
      debugPrint('Mensaje recibido en primer plano: ${message.notification?.title}');
      if (_trustSosPayload(message) != null) {
        _showTrustSos(message);
        return;
      }
      
      RemoteNotification? notification = message.notification;
      AndroidNotification? android = message.notification?.android;
      final isVerified = message.data['type'] == 'report_verified' || message.data['type'] == 'case_update';
      final alertId = _ambulanceId(message);
      final assignmentId = _assignmentId(message);
      final caseId = _caseId(message);
      final reportId = _nearbyReportId(message);
      final missingCaseId = _missingCaseId(message);
      final emergencyChannel = switch (message.data['emergency_sound']) {
        'siren' => _emergencySiren,
        'silent' => _emergencySilent,
        _ => null,
      };
      if (alertId != null) unawaited(_recordReceipt(alertId, 'foreground'));
      final playedSiren = alertId != null &&
          !kIsWeb && defaultTargetPlatform == TargetPlatform.android &&
          await _playAmbulanceSiren();

      if (_disposed) return;
      if (notification != null && (android != null ||
          ((alertId != null || emergencyChannel != null || missingCaseId != null) &&
              defaultTargetPlatform == TargetPlatform.android))) {
        _localNotificationsPlugin.show(
          id: (assignmentId == null ? alertId?.hashCode : 'dispatch:$assignmentId'.hashCode) ?? notification.hashCode,
          title: notification.title,
          body: notification.body,
          notificationDetails: NotificationDetails(
            android: AndroidNotificationDetails(
              emergencyChannel?.id ?? (alertId != null ? _ambulanceChannel : _generalChannel.id),
              emergencyChannel?.name ?? (alertId != null ? 'Avisos de ambulancias' : _generalChannel.name),
              channelDescription: alertId != null ? 'Avisos cuando una ambulancia se aproxima a tu ubicación.' : isVerified
                  ? 'Cuando cambia el estado de tu reporte'
                  : 'Canal para alertas de incidentes cercanos',
              importance: Importance.max,
              priority: Priority.high,
              playSound: emergencyChannel?.playSound ?? true,
              silent: playedSiren || emergencyChannel == _emergencySilent,
              audioAttributesUsage: alertId != null ? AudioAttributesUsage.alarm : AudioAttributesUsage.notification,
              sound: alertId != null ? _ambulanceSound : (emergencyChannel ?? _generalChannel).sound,
              icon: '@mipmap/ic_launcher',
            ),
          ),
          payload: missingCaseId != null
              ? 'missing-case:$missingCaseId'
              : assignmentId != null
              ? 'dispatch:$assignmentId'
              : (caseId != null ? 'case:$caseId' : (reportId != null ? 'report:$reportId' : alertId)),
        );
      }
      
      if (assignmentId != null) _onAssignmentOpened?.call(assignmentId);
      if (alertId == null) onMessageReceived();
    });
  }

  Future<bool> _playAmbulanceSiren() async {
    try {
      return await _ambulanceAudio.invokeMethod<bool>('play') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  String? _ambulanceId(RemoteMessage message) =>
      message.data['type'] == 'ambulance_approaching'
          ? message.data['alert_id'] as String?
          : null;

  void _recordOpened(RemoteMessage message) {
    final missingCaseId = _missingCaseId(message);
    if (missingCaseId != null) {
      _onMissingCaseOpened?.call(missingCaseId);
      return;
    }
    final reportId = _nearbyReportId(message);
    if (reportId != null) {
      _onReportOpened?.call(reportId);
      return;
    }
    final caseId = _caseId(message);
    if (caseId != null) {
      _onCaseOpened?.call(caseId);
      return;
    }
    final assignmentId = _assignmentId(message);
    if (assignmentId != null) {
      _onAssignmentOpened?.call(assignmentId);
      return;
    }
    final alertId = _ambulanceId(message);
    if (alertId != null) unawaited(_recordReceipt(alertId, 'opened'));
    final sos = _trustSosPayload(message);
    if (sos != null) unawaited(_openSosLocation(sos));
  }

  int? _assignmentId(RemoteMessage message) => message.data['type'] == 'ambulance_assignment'
      ? int.tryParse('${message.data['dispatch_id']}')
      : null;

  /// HU-26: push de seguimiento del caso (verificado, unidad asignada, en camino...).
  int? _caseId(RemoteMessage message) => message.data['type'] == 'case_update'
      ? int.tryParse('${message.data['reportId']}')
      : null;

  int? _nearbyReportId(RemoteMessage message) =>
      message.data['type'] == 'nearby_report'
          ? int.tryParse('${message.data['reportId']}')
          : null;

  int? _missingCaseId(RemoteMessage message) => const {'missing_case', 'missing_case_update'}.contains(message.data['type'])
      ? int.tryParse('${message.data['missingCaseId']}')
      : null;

  void _openPayload(String? payload) {
    if (_disposed || payload == null) return;
    if (payload.startsWith('missing-case:')) {
      final id = int.tryParse(payload.substring('missing-case:'.length));
      if (id != null) _onMissingCaseOpened?.call(id);
      return;
    }
    if (payload.startsWith('report:')) {
      final id = int.tryParse(payload.substring('report:'.length));
      if (id != null) _onReportOpened?.call(id);
      return;
    }
    if (payload.startsWith('case:')) {
      final id = int.tryParse(payload.substring('case:'.length));
      if (id != null) _onCaseOpened?.call(id);
      return;
    }
    if (payload.startsWith('dispatch:')) {
      final id = int.tryParse(payload.substring('dispatch:'.length));
      if (id != null) _onAssignmentOpened?.call(id);
    } else if (payload.startsWith('sos:')) {
      unawaited(_openSosLocation(payload));
    } else {
      unawaited(_recordReceipt(payload, 'opened'));
    }
  }

  /// HU-25: aviso del círculo de confianza (alguien activó el SOS). Devuelve "sos:lat,lng".
  String? _trustSosPayload(RemoteMessage message) {
    if (message.data['type'] != 'trust_circle_sos') return null;
    final lat = double.tryParse('${message.data['latitude']}');
    final lng = double.tryParse('${message.data['longitude']}');
    return lat == null || lng == null ? null : 'sos:$lat,$lng';
  }

  void _showTrustSos(RemoteMessage message) {
    final payload = _trustSosPayload(message);
    if (payload == null || _disposed) return;
    _localNotificationsPlugin.show(
      id: payload.hashCode,
      title: message.notification?.title ?? 'Tu contacto necesita ayuda',
      body: message.notification?.body ?? 'Toca para ver su ubicación.',
      notificationDetails: const NotificationDetails(
        android: AndroidNotificationDetails(
          'trust_circle_sound_v2',
          'Círculo de confianza',
          channelDescription: 'Cuando alguien de tu círculo activa el botón de pánico',
          importance: Importance.max,
          priority: Priority.high,
          playSound: true,
          sound: _notificationSound,
          icon: '@mipmap/ic_launcher',
        ),
      ),
      payload: payload,
    );
  }

  /// Abre la ubicación del SOS en Google Maps.
  Future<void> _openSosLocation(String payload) async {
    final coords = payload.substring('sos:'.length);
    try {
      await launchUrl(Uri.parse('https://maps.google.com/?q=$coords'), mode: LaunchMode.externalApplication);
    } catch (error) {
      debugPrint('No se pudo abrir la ubicación del SOS: ${error.runtimeType}');
    }
  }

  Future<void> _recordReceipt(String alertId, String event) async {
    try {
      await dioClient.dio.post('/ambulance-alerts/$alertId/receipt', data: {'event': event});
    } catch (error) {
      debugPrint('No se pudo registrar la recepción del aviso: ${error.runtimeType}');
    }
  }

  void dispose() {
    _disposed = true;
    _tokenUpdates?.cancel();
    _foregroundMessages?.cancel();
    _openedMessages?.cancel();
  }
}



