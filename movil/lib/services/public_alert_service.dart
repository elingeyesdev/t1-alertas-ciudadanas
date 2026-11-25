import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/models/public_alert_model.dart';

/// HU-32: avisos preventivos vigentes para dibujarlos en el mapa.
class PublicAlertService {
  Future<List<PublicAlertModel>> findActive() async {
    final response = await dioClient.dio.get('/public-alerts');
    final data = response.data as List<dynamic>;
    final now = DateTime.now();
    return data
        .map((e) => PublicAlertModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .where((alert) => alert.hasLocation && alert.isActiveAt(now))
        .toList();
  }
}
