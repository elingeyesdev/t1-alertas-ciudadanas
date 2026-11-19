import 'package:alertas_ciudadanas/core/network/dio_client.dart';

/// HU-24: botón de pánico.
class SosService {
  /// Envía el SOS. El servidor crea la alerta prioritaria, asigna la unidad
  /// más cercana y avisa al círculo de confianza.
  Future<SosResult> trigger({
    required double latitude,
    required double longitude,
    String kind = 'general',
    String? zone,
  }) async {
    final response = await dioClient.dio.post('/sos', data: {
      'latitude': latitude,
      'longitude': longitude,
      'kind': kind,
      if (zone != null && zone.isNotEmpty) 'zone': zone,
    });
    final data = response.data is Map ? Map<String, dynamic>.from(response.data as Map) : <String, dynamic>{};
    return SosResult.fromJson(data);
  }

  /// «Ya estoy a salvo»: cierra la alerta.
  Future<void> markSafe(int reportId) async {
    await dioClient.dio.patch('/reports/$reportId/resolve');
  }
}

class SosResult {
  final int reportId;
  final bool existing;
  final bool unitAssigned;
  final String? stationName;
  final int circlePushed;
  final List<SosWhatsAppContact> whatsapp;

  const SosResult({
    required this.reportId,
    required this.existing,
    required this.unitAssigned,
    required this.stationName,
    required this.circlePushed,
    required this.whatsapp,
  });

  factory SosResult.fromJson(Map<String, dynamic> json) {
    final report = json['report'] is Map ? Map<String, dynamic>.from(json['report'] as Map) : <String, dynamic>{};
    final dispatch = json['dispatch'] is Map ? Map<String, dynamic>.from(json['dispatch'] as Map) : null;
    final circle = json['circle'] is Map ? Map<String, dynamic>.from(json['circle'] as Map) : <String, dynamic>{};
    return SosResult(
      reportId: (report['id'] as num?)?.toInt() ?? 0,
      existing: json['existing'] == true,
      unitAssigned: dispatch != null,
      stationName: dispatch?['station']?.toString(),
      circlePushed: (circle['pushed'] as num?)?.toInt() ?? 0,
      whatsapp: (circle['whatsapp'] as List? ?? const [])
          .whereType<Map>()
          .map((e) => SosWhatsAppContact.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}

/// Contacto del círculo sin la app: se le avisa abriendo WhatsApp.
class SosWhatsAppContact {
  final String name;
  final String url;

  const SosWhatsAppContact({required this.name, required this.url});

  factory SosWhatsAppContact.fromJson(Map<String, dynamic> json) => SosWhatsAppContact(
        name: (json['name'] ?? '').toString(),
        url: (json['url'] ?? '').toString(),
      );
}
