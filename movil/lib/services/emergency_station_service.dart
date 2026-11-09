import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/models/emergency_station_model.dart';

class EmergencyStationService {
  Future<List<EmergencyStationModel>> findAll() async {
    final response = await dioClient.dio.get('/emergency-stations');
    final data = response.data as List<dynamic>;
    return data
        .map((e) => EmergencyStationModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }
}
