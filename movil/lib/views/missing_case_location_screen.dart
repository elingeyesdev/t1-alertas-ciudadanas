import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../core/config/mapbox_config.dart';

class MissingCaseLocationScreen extends StatefulWidget {
  const MissingCaseLocationScreen({
    super.key,
    required this.center,
    this.selectedPoint,
  });

  final LatLng center;
  final LatLng? selectedPoint;

  @override
  State<MissingCaseLocationScreen> createState() =>
      _MissingCaseLocationScreenState();
}

class _MissingCaseLocationScreenState extends State<MissingCaseLocationScreen> {
  late LatLng? _point = widget.selectedPoint;

  @override
  Widget build(BuildContext context) {
    final point = _point;
    return Scaffold(
      appBar: AppBar(title: const Text('Ubicación de la desaparición')),
      body: Column(
        children: [
          const Padding(
            padding: EdgeInsets.all(12),
            child: Text('Toca el mapa para marcar el lugar de desaparición.'),
          ),
          Expanded(
            child: FlutterMap(
              options: MapOptions(
                initialCenter: point ?? widget.center,
                initialZoom: 15,
                maxZoom: 19,
                onTap: (_, location) => setState(
                  () => _point = LatLng(
                    location.latitude.clamp(-90.0, 90.0),
                    ((location.longitude + 180) % 360) - 180,
                  ),
                ),
              ),
              children: [
                MapboxConfig.tileLayer(context),
                if (point != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: point,
                        alignment: Alignment.topCenter,
                        height: 44,
                        width: 44,
                        child: Icon(
                          Icons.location_on,
                          size: 44,
                          color: Theme.of(context).colorScheme.primary,
                          semanticLabel: 'Ubicación seleccionada',
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            minimum: const EdgeInsets.all(12),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: point == null
                    ? null
                    : () => Navigator.of(context).pop(point),
                child: const Text('Confirmar ubicación'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
