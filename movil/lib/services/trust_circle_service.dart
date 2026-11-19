import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/models/trust_circle_model.dart';

/// HU-25: API del círculo de confianza.
class TrustCircleService {
  Map<String, dynamic> _map(dynamic data) =>
      data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};

  Future<TrustCircleModel> fetch() async {
    final response = await dioClient.dio.get('/trust-circle');
    return TrustCircleModel.fromJson(_map(response.data));
  }

  Future<void> addContact(String name, String phone) async {
    await dioClient.dio.post('/trust-circle/contacts', data: {'name': name, 'phone': phone});
  }

  Future<void> updateContact(int id, {required String name, String? phone}) async {
    await dioClient.dio.patch('/trust-circle/contacts/$id', data: {
      'name': name,
      if (phone != null) 'phone': phone,
    });
  }

  Future<void> removeContact(int id) async {
    await dioClient.dio.delete('/trust-circle/contacts/$id');
  }

  Future<TrustInviteModel> createInvite() async {
    final response = await dioClient.dio.post('/trust-circle/invites');
    return TrustInviteModel.fromJson(_map(response.data));
  }

  /// Devuelve el nombre de la persona a cuyo círculo me uní.
  Future<String> join(String code) async {
    final response = await dioClient.dio.post('/trust-circle/join', data: {'code': code});
    return (_map(response.data)['owner_name'] ?? '').toString();
  }

  Future<void> leave(int membershipId) async {
    await dioClient.dio.delete('/trust-circle/memberships/$membershipId');
  }
}
