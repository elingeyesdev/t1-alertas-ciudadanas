import 'dart:async';

import 'package:alertas_ciudadanas/core/route_corridor.dart';
import 'package:alertas_ciudadanas/services/risk_zone_monitor_service.dart';
import 'package:alertas_ciudadanas/services/tracking_service.dart';
import 'package:latlong2/latlong.dart';

class RouteCorridorMonitorService {
  RouteCorridorMonitorService._();
  static final RouteCorridorMonitorService instance =
      RouteCorridorMonitorService._();

  final TrackingService _trackingService = TrackingService();
  StreamSubscription<List<Map<String, dynamic>>>? _trackingSub;
  String? _currentUserId;
  bool _includeAmbulances = true;

  Future<void> start(String? userId, {required bool includeAmbulances}) async {
    _currentUserId = userId;
    _includeAmbulances = includeAmbulances;
    await _trackingSub?.cancel();
    _trackingSub = _trackingService.streamTrackings().listen(_onTrackings);
  }

  Future<void> stop() async {
    await _trackingSub?.cancel();
    _trackingSub = null;
    await RiskZoneMonitorService.instance.syncCorridors(const []);
  }

  void _onTrackings(List<Map<String, dynamic>> vehicles) {
    final corridors = <RouteCorridor>[];

    for (final vehicle in vehicles) {
      if (vehicle['id'] == _currentUserId) continue;
      if (!_includeAmbulances && vehicle['profileType'] == 'paramedico') continue;

      final status = (vehicle['status'] ?? '').toString();
      if (status != 'active' && status != 'planned') continue;

      final routeRaw = vehicle['route'];
      if (routeRaw is! List || routeRaw.length < 2) continue;

      final parsedPoints = <LatLng>[];
      for (final point in routeRaw) {
        if (point is! Map) continue;
        final lat = (point['lat'] as num?)?.toDouble();
        final lng = (point['lng'] as num?)?.toDouble();
        if (lat == null || lng == null) continue;
        parsedPoints.add(LatLng(lat, lng));
      }

      if (parsedPoints.length < 2) continue;

      corridors.add(
        RouteCorridor(
          id: (vehicle['id'] ?? '').toString(),
          label: (vehicle['type'] ?? 'Emergencia').toString(),
          points: parsedPoints,
          activeAmbulance: status == 'active' && vehicle['profileType'] == 'paramedico',
        ),
      );
    }

    unawaited(
      RiskZoneMonitorService.instance.syncCorridors(
        corridors.map((c) => c.toJson()).toList(),
      ),
    );
  }
}
