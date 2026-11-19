/// HU-25: círculo de confianza (GET /trust-circle).
class TrustCircleModel {
  final int maxContacts;
  final List<TrustContactModel> contacts;
  final List<TrustMembershipModel> memberships;

  const TrustCircleModel({required this.maxContacts, required this.contacts, required this.memberships});

  bool get isFull => contacts.length >= maxContacts;

  factory TrustCircleModel.fromJson(Map<String, dynamic> json) => TrustCircleModel(
        maxContacts: (json['max_contacts'] as num?)?.toInt() ?? 5,
        contacts: (json['contacts'] as List? ?? const [])
            .whereType<Map>()
            .map((e) => TrustContactModel.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
        memberships: (json['memberships'] as List? ?? const [])
            .whereType<Map>()
            .map((e) => TrustMembershipModel.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
}

class TrustContactModel {
  final int id;
  final String name;
  final String phone;

  /// true: tiene la app y recibe push; false: se le avisa por WhatsApp.
  final bool hasApp;

  const TrustContactModel({required this.id, required this.name, required this.phone, required this.hasApp});

  factory TrustContactModel.fromJson(Map<String, dynamic> json) => TrustContactModel(
        id: (json['id'] as num?)?.toInt() ?? 0,
        name: (json['name'] ?? '').toString(),
        phone: (json['phone'] ?? '').toString(),
        hasApp: json['has_app'] == true,
      );
}

/// Círculo de otra persona al que pertenezco.
class TrustMembershipModel {
  final int id;
  final String ownerName;

  const TrustMembershipModel({required this.id, required this.ownerName});

  factory TrustMembershipModel.fromJson(Map<String, dynamic> json) => TrustMembershipModel(
        id: (json['id'] as num?)?.toInt() ?? 0,
        ownerName: (json['owner_name'] ?? '').toString(),
      );
}

class TrustInviteModel {
  final String code;
  final DateTime? expiresAt;
  final String shareText;

  const TrustInviteModel({required this.code, required this.expiresAt, required this.shareText});

  factory TrustInviteModel.fromJson(Map<String, dynamic> json) => TrustInviteModel(
        code: (json['code'] ?? '').toString(),
        expiresAt: DateTime.tryParse((json['expires_at'] ?? '').toString())?.toLocal(),
        shareText: (json['share_text'] ?? '').toString(),
      );
}
