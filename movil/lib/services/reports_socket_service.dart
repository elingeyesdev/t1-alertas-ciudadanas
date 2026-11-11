import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:alertas_ciudadanas/models/alert_model.dart';
import 'package:alertas_ciudadanas/services/realtime_client.dart';

/// Nuevos reportes en tiempo real.
/// Antes: socket.io, namespace "reports", evento "newReport".
/// Ahora: Laravel Reverb, canal público "reports", evento "newReport".
class ReportsSocketService {
  static final ReportsSocketService _instance = ReportsSocketService._internal();
  factory ReportsSocketService() => _instance;

  ReportsSocketService._internal();

  static const String _channel = 'reports';

  final RealtimeClient _realtime = RealtimeClient();
  final _reportsController = StreamController<AlertModel>.broadcast();
  bool _isConnected = false;

  void connect() {
    if (_isConnected) {
      debugPrint('Tiempo real de reportes ya está activo');
      return;
    }
    _isConnected = true;

    _realtime.listen(_channel, 'newReport', (data) {
      try {
        if (data.isNotEmpty) {
          _reportsController.add(AlertModel.fromJson(data));
        }
      } catch (e) {
        debugPrint('Error parseando nuevo reporte en tiempo real: $e');
      }
    });
  }

  /// Escucha eventos de nuevos reportes creados.
  Stream<AlertModel> streamNewReports() {
    return _reportsController.stream;
  }

  void disconnect() {
    _realtime.stopListening(_channel);
    _isConnected = false;
    debugPrint('Tiempo real de reportes cerrado por sesión terminada.');
  }

  void dispose() {
    // Para retrocompatibilidad y evitar errores si se llama desde vistas antiguas.
    // No desconectamos el singleton completo aquí para no interrumpir otras vistas.
  }
}
