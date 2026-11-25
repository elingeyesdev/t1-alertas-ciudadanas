import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:alertas_ciudadanas/models/public_alert_model.dart';

/// HU-32: cómo se ven los avisos preventivos en el mapa del ciudadano.
Color publicAlertColor(String category) {
  switch (category) {
    case 'salud':
      return const Color(0xFFD6336C);
    case 'viaje':
      return const Color(0xFFF76707);
    default:
      return const Color(0xFFAE3EC9);
  }
}

IconData publicAlertIcon(String category) {
  switch (category) {
    case 'salud':
      return Icons.health_and_safety_rounded;
    case 'viaje':
      return Icons.directions_car_rounded;
    default:
      return Icons.thunderstorm_rounded;
  }
}

/// Zona de cada aviso: un círculo con el radio real en metros.
List<CircleMarker<Object>> publicAlertCircles(List<PublicAlertModel> alerts) {
  return alerts
      .where((alert) => alert.hasLocation)
      .map(
        (alert) => CircleMarker<Object>(
          point: LatLng(alert.latitude, alert.longitude),
          radius: alert.radiusMeters.toDouble(),
          useRadiusInMeter: true,
          color: publicAlertColor(alert.category).withValues(alpha: 0.12),
          borderColor: publicAlertColor(alert.category),
          borderStrokeWidth: 2,
        ),
      )
      .toList();
}

/// Ícono en el centro de la zona; al tocarlo se abre el detalle.
List<Marker> publicAlertMarkers(
  List<PublicAlertModel> alerts,
  void Function(PublicAlertModel alert) onTap,
) {
  return alerts
      .where((alert) => alert.hasLocation)
      .map(
        (alert) => Marker(
          point: LatLng(alert.latitude, alert.longitude),
          width: 36,
          height: 36,
          child: GestureDetector(
            onTap: () => onTap(alert),
            child: Container(
              decoration: BoxDecoration(
                color: publicAlertColor(alert.category),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
              ),
              child: Icon(publicAlertIcon(alert.category), color: Colors.white, size: 20),
            ),
          ),
        ),
      )
      .toList();
}

String _twoDigits(int value) => value.toString().padLeft(2, '0');

String formatPublicAlertExpiry(DateTime date) =>
    '${_twoDigits(date.day)}/${_twoDigits(date.month)}/${date.year} ${_twoDigits(date.hour)}:${_twoDigits(date.minute)}';

String formatPublicAlertRadius(int meters) =>
    meters >= 1000 ? '${(meters / 1000).toStringAsFixed(meters % 1000 == 0 ? 0 : 1)} km' : '$meters m';

/// Detalle del aviso: tipo, título, mensaje, zona y vigencia.
Future<void> showPublicAlertSheet(BuildContext context, PublicAlertModel alert) {
  final color = publicAlertColor(alert.category);
  return showModalBottomSheet(
    context: context,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    showDragHandle: true,
    isScrollControlled: true,
    builder: (context) {
      final onSurface = Theme.of(context).colorScheme.onSurface;
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(publicAlertIcon(alert.category), color: color, size: 26),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Aviso preventivo · ${alert.categoryLabel}',
                          style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          alert.title,
                          style: TextStyle(color: onSurface, fontSize: 18, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(alert.message, style: TextStyle(color: onSurface, fontSize: 14, height: 1.4)),
              const SizedBox(height: 14),
              Row(
                children: [
                  Icon(Icons.location_on_rounded, size: 16, color: onSurface.withValues(alpha: 0.7)),
                  const SizedBox(width: 6),
                  Text(
                    'Zona de ${formatPublicAlertRadius(alert.radiusMeters)} alrededor del punto',
                    style: TextStyle(color: onSurface.withValues(alpha: 0.8), fontSize: 12),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Icon(Icons.schedule_rounded, size: 16, color: onSurface.withValues(alpha: 0.7)),
                  const SizedBox(width: 6),
                  Text(
                    'Vigente hasta el ${formatPublicAlertExpiry(alert.expiresAt)}',
                    style: TextStyle(color: onSurface.withValues(alpha: 0.8), fontSize: 12),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    },
  );
}
