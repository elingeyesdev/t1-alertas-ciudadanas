import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/models/case_tracking_model.dart';

/// HU-26: consulta el seguimiento de un reporte propio.
class CaseService {
  Future<CaseTrackingModel> fetchCase(int reportId) async {
    final response = await dioClient.dio.get('/reports/$reportId/case');
    final data = response.data;
    final json = data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    return CaseTrackingModel.fromJson(json);
  }
}
