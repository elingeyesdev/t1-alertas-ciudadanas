import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/services/realtime_client.dart';

/// Tracking en vivo de unidades de emergencia.
///
/// Antes todo iba por socket.io (getTrackings, startTracking, stopTracking).
/// Ahora:
///  - enviar posición:  POST /api/trackings/start
///  - detener:          POST /api/trackings/stop
///  - lista actual:     GET  /api/trackings
///  - cambios en vivo:  canal Reverb "trackings" (tracking_started / tracking_stopped)
class TrackingService {
  static final TrackingService _instance = TrackingService._internal();
  factory TrackingService() => _instance;

  TrackingService._internal();

  static const String _channel = 'trackings';

  final RealtimeClient _realtime = RealtimeClient();
  final _trackingsController = StreamController<List<Map<String, dynamic>>>.broadcast();
  final List<Map<String, dynamic>> _activeTrackings = [];
  StreamSubscription<bool>? _connectionSub;
  Timer? _pollTimer;
  bool _isConnected = false;

  void connect() {
    if (_isConnected) {
      debugPrint('Tiempo real de tracking ya está activo');
      return;
    }
    _isConnected = true;

    _realtime.listen(_channel, 'tracking_started', (data) {
      if (data.isEmpty) return;
      final tracking = Map<String, dynamic>.from(data);
      tracking['id'] ??= tracking['userId'];
      final index = _activeTrackings.indexWhere((t) => t['id'] == tracking['id']);
      if (index != -1) {
        _activeTrackings[index] = tracking;
      } else {
        _activeTrackings.add(tracking);
      }
      _emit();
    });

    _realtime.listen(_channel, 'tracking_stopped', (data) {
      final userId = data['userId']?.toString();
      if (userId != null) {
        _activeTrackings.removeWhere((t) => t['id'] == userId);
        _emit();
      }
    });

    // Al (re)conectar se pide la lista actual (antes: emit 'getTrackings')
    _connectionSub = _realtime.connectionChanges.listen((connected) {
      if (connected) _loadTrackings();
    });

    // Respaldo: refresca la lista cada 30 s
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) => _loadTrackings());
    _loadTrackings();
  }

  Future<void> _loadTrackings() async {
    try {
      final response = await dioClient.dio.get('/trackings');
      final data = response.data;
      if (data is List) {
        _activeTrackings
          ..clear()
          ..addAll(data.whereType<Map>().map((item) => Map<String, dynamic>.from(item)));
        _emit();
      }
    } catch (e) {
      debugPrint('Error obteniendo trackings: $e');
    }
  }

  void _emit() {
    _trackingsController.add(List.from(_activeTrackings));
  }

  /// Crea o actualiza el tracking en tiempo real.
  /// [trackingId] es el ID de usuario o un ID único por vehículo.
  /// [data] contiene latitud, longitud, tipo de vehículo y la ruta.
  Future<void> startTracking(String trackingId, Map<String, dynamic> data) async {
    try {
      await dioClient.dio.post('/trackings/start', data: {
        ...data,
        'userId': trackingId,
      });
      debugPrint('Tracking enviado: $trackingId (${data['status']})');
    } catch (e) {
      debugPrint('Error enviando tracking: $e');
    }
  }

  /// Elimina el tracking para que desaparezca del mapa.
  Future<void> stopTracking(String trackingId) async {
    try {
      await dioClient.dio.post('/trackings/stop', data: {'userId': trackingId});
      debugPrint('Tracking detenido: $trackingId');
    } catch (e) {
      debugPrint('Error deteniendo tracking: $e');
    }
  }

  /// Escucha todos los vehículos de emergencia activos.
  Stream<List<Map<String, dynamic>>> streamTrackings() {
    _loadTrackings();
    return _trackingsController.stream;
  }

  void disconnect() {
    _pollTimer?.cancel();
    _pollTimer = null;
    _connectionSub?.cancel();
    _connectionSub = null;
    _realtime.stopListening(_channel);
    _activeTrackings.clear();
    _isConnected = false;
    debugPrint('Tiempo real de tracking cerrado por sesión terminada.');
  }
}
