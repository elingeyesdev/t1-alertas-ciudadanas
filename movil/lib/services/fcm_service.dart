import 'dart:async';

import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:alertas_ciudadanas/services/user_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class FcmService {
  static const _ambulanceChannel = 'ambulance_alerts_sound_v3';
  static const _ambulanceAudio = MethodChannel('alertas/ambulance_audio');
  static const _ambulanceSound = RawResourceAndroidNotificationSound('ambulance_proximity_siren');
  StreamSubscription<String>? _tokenUpdates;
  StreamSubscription<RemoteMessage>? _foregroundMessages;
  StreamSubscription<RemoteMessage>? _openedMessages;
  bool _disposed = false;
  void Function(int)? _onAssignmentOpened;
  void Function(int)? _onCaseOpened;
  /// true si Firebase se inicializó (existe google-services.json)
  bool get _firebaseReady => Firebase.apps.isNotEmpty;

  FirebaseMessaging get _firebaseMessaging => FirebaseMessaging.instance;
  final UserService _userService = UserService();
  final FlutterLocalNotificationsPlugin _localNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  Future<void> init(String userId, {void Function(int)? onAssignmentOpened, void Function(int)? onCaseOpened}) async {
    _onAssignmentOpened = onAssignmentOpened;
    _onCaseOpened = onCaseOpened;
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
      await _firebaseMessaging.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );
      if (_disposed) return;
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
      
      RemoteNotification? notification = message.notification;
      AndroidNotification? android = message.notification?.android;
      final isVerified = message.data['type'] == 'report_verified' || message.data['type'] == 'case_update';
      final alertId = _ambulanceId(message);
      final assignmentId = _assignmentId(message);
      final caseId = _caseId(message);
      if (alertId != null) unawaited(_recordReceipt(alertId, 'foreground'));
      final playedSiren = alertId != null &&
          !kIsWeb && defaultTargetPlatform == TargetPlatform.android &&
          await _playAmbulanceSiren();

      if (_disposed) return;
      if (notification != null && (android != null || (alertId != null && defaultTargetPlatform == TargetPlatform.android))) {
        _localNotificationsPlugin.show(
          id: (assignmentId == null ? alertId?.hashCode : 'dispatch:$assignmentId'.hashCode) ?? notification.hashCode,
          title: notification.title,
          body: notification.body,
          notificationDetails: NotificationDetails(
            android: AndroidNotificationDetails(
              alertId != null ? _ambulanceChannel : (isVerified ? 'verified_channel' : 'alert_channel'),
              alertId != null ? 'Avisos de ambulancias' : (isVerified ? 'Verificaciones' : 'Alertas'),
              channelDescription: alertId != null ? 'Avisos cuando una ambulancia se aproxima a tu ubicación.' : isVerified
                  ? 'Cuando cambia el estado de tu reporte'
                  : 'Canal para alertas de incidentes cercanos',
              importance: Importance.max,
              priority: Priority.high,
              playSound: true,
              silent: playedSiren,
              audioAttributesUsage: alertId != null ? AudioAttributesUsage.alarm : AudioAttributesUsage.notification,
              sound: alertId != null ? _ambulanceSound : null,
              icon: '@mipmap/ic_launcher',
            ),
          ),
          payload: assignmentId != null
              ? 'dispatch:$assignmentId'
              : (caseId != null ? 'case:$caseId' : alertId),
        );
      }
      
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
  }

  int? _assignmentId(RemoteMessage message) => message.data['type'] == 'ambulance_assignment'
      ? int.tryParse('${message.data['dispatch_id']}')
      : null;

  /// HU-26: push de seguimiento del caso (verificado, unidad asignada, en camino...).
  int? _caseId(RemoteMessage message) => message.data['type'] == 'case_update'
      ? int.tryParse('${message.data['reportId']}')
      : null;

  void _openPayload(String? payload) {
    if (_disposed || payload == null) return;
    if (payload.startsWith('case:')) {
      final id = int.tryParse(payload.substring('case:'.length));
      if (id != null) _onCaseOpened?.call(id);
      return;
    }
    if (payload.startsWith('dispatch:')) {
      final id = int.tryParse(payload.substring('dispatch:'.length));
      if (id != null) _onAssignmentOpened?.call(id);
    } else {
      unawaited(_recordReceipt(payload, 'opened'));
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



