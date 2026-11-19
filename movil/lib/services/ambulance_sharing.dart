import 'dart:async';

import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/services/location_service.dart';
import 'package:flutter/widgets.dart';
import 'package:geolocator/geolocator.dart';

class AmbulanceSharing extends ChangeNotifier with WidgetsBindingObserver {
  AmbulanceSharing._();
  static final instance = AmbulanceSharing._();

  bool? enabled;
  String? _userId;
  Timer? _timer;
  bool _sampling = false;

  Future<void> attach(String userId) async {
    if (_userId == userId) return;
    detach(_userId);
    _userId = userId;
    WidgetsBinding.instance.addObserver(this);
    try {
      final response = await dioClient.dio.get('/ambulance-alerts/consent');
      if (_userId != userId) return;
      enabled = response.data['enabled'] == true;
      notifyListeners();
      _refreshSampling();
    } catch (error) {
      debugPrint('No se pudo consultar el permiso de ambulancias: ${error.runtimeType}');
    }
  }

  Future<void> setEnabled(bool value) async {
    final userId = _userId;
    if (userId == null) return;
    if (value) await const LocationService().ensureLocationAccess();
    if (_userId != userId) return;
    await dioClient.dio.put('/ambulance-alerts/consent', data: {'enabled': value});
    if (_userId != userId) return;
    enabled = value;
    notifyListeners();
    _refreshSampling();
  }

  void detach(String? userId) {
    if (_userId == null || _userId != userId) return;
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _timer = null;
    _userId = null;
    enabled = null;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) => _refreshSampling();

  void _refreshSampling() {
    _timer?.cancel();
    _timer = null;
    if (enabled != true || WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
      return;
    }
    unawaited(_sample());
    _timer = Timer.periodic(const Duration(seconds: 12), (_) => _sample());
  }

  Future<void> _sample() async {
    if (_sampling) return;
    _sampling = true;
    final userId = _userId;
    try {
      final permission = await Geolocator.checkPermission();
      if (permission != LocationPermission.always && permission != LocationPermission.whileInUse) {
        if (_userId == userId && enabled == true) await setEnabled(false);
        return;
      }
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 8),
      );
      final DateTime? capturedAt = position.timestamp;
      if (_userId != userId || enabled != true || _timer == null ||
          capturedAt == null || position.accuracy < 0 || position.accuracy > 30) {
        return;
      }
      await dioClient.dio.post('/ambulance-alerts/position', data: {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy_m': position.accuracy,
        'captured_at': capturedAt.toUtc().toIso8601String(),
      });
    } catch (error) {
      debugPrint('No se pudo actualizar la ubicación para ambulancias: ${error.runtimeType}');
    } finally {
      _sampling = false;
    }
  }
}
