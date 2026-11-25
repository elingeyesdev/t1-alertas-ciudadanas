/// HU-32: aviso preventivo (salud, viaje o desastre natural) que publica el
/// administrador para una zona circular y con fecha de fin.
class PublicAlertModel {
  final int id;
  final String category;
  final String categoryLabel;
  final String title;
  final String message;

  /// [longitud, latitud], igual que el resto de la API.
  final List<double> coordinates;
  final int radiusMeters;
  final DateTime expiresAt;

  const PublicAlertModel({
    required this.id,
    required this.category,
    required this.categoryLabel,
    required this.title,
    required this.message,
    required this.coordinates,
    required this.radiusMeters,
    required this.expiresAt,
  });

  factory PublicAlertModel.fromJson(Map<String, dynamic> json) {
    final rawCoords = json['coordinates'] as List<dynamic>?;
    return PublicAlertModel(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      category: (json['category'] ?? '').toString(),
      categoryLabel: (json['category_label'] ?? json['category'] ?? '').toString(),
      title: (json['title'] ?? '').toString(),
      message: (json['message'] ?? '').toString(),
      coordinates: rawCoords != null
          ? rawCoords.map((e) => (e as num).toDouble()).toList()
          : const <double>[],
      radiusMeters: (json['radius_m'] as num?)?.round() ?? 0,
      expiresAt: DateTime.tryParse(json['expires_at']?.toString() ?? '')?.toLocal() ??
          DateTime.fromMillisecondsSinceEpoch(0),
    );
  }

  bool get hasLocation => coordinates.length >= 2;

  double get latitude => coordinates[1];

  double get longitude => coordinates[0];

  /// Vigente si todavía no llegó su fecha de fin.
  bool isActiveAt(DateTime now) => expiresAt.isAfter(now);
}
