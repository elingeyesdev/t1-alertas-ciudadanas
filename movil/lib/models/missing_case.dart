import 'package:latlong2/latlong.dart';

enum MissingCaseKind {
  person('Persona', 'Personas'),
  pet('Mascota', 'Mascotas');

  const MissingCaseKind(this.label, this.plural);

  final String label;
  final String plural;
}

enum MissingCaseStatus {
  pending('Pendiente de revisión'),
  published('Publicado'),
  rejected('Rechazado'),
  found('Encontrado');

  const MissingCaseStatus(this.label);

  final String label;

  bool get isPublic => this == published || this == found;
}

class MissingCase {
  const MissingCase({
    required this.id,
    required this.kind,
    required this.status,
    required this.name,
    required this.place,
    required this.missingOn,
    required this.description,
    required this.referencePhone,
    required this.photoCount,
    required this.evidence,
  });

  factory MissingCase.fromJson(Map<String, dynamic> json) => MissingCase(
    id: json['id'] as int,
    kind: MissingCaseKind.values.byName(json['kind'] as String),
    status: MissingCaseStatus.values.byName(json['status'] as String),
    name: json['name'] as String,
    place: json['place'] as String,
    missingOn: DateTime.parse(json['missing_on'] as String),
    description: json['description'] as String,
    referencePhone: json['reference_phone'] as String?,
    photoCount: json['photo_count'] as int,
    evidence: (json['evidence'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>()
        .map(MissingCaseMedia.fromJson)
        .toList(),
  );

  final int id;
  final MissingCaseKind kind;
  final MissingCaseStatus status;
  final String name;
  final String place;
  final DateTime missingOn;
  final String description;
  final String? referencePhone;
  final int photoCount;
  final List<MissingCaseMedia> evidence;

  bool get found => status == MissingCaseStatus.found;
}

class MissingCaseMedia {
  MissingCaseMedia.fromJson(Map<String, dynamic> json)
    : isVideo = json['type'] == 'video',
      index = json['index'] as int?,
      contributionId = json['contribution_id'] as int?;

  final bool isVideo;
  final int? index;
  final int? contributionId;

  String get key => '$contributionId/$index/$isVideo';
}

class MissingCasePin {
  MissingCasePin.fromJson(Map<String, dynamic> json)
    : id = json['id'] as int,
      name = json['name'] as String,
      kind = MissingCaseKind.values.byName(json['kind'] as String),
      found = json['status'] == 'found',
      point = LatLng(
        (json['latitude'] as num).toDouble(),
        (json['longitude'] as num).toDouble(),
      );

  final int id;
  final String name;
  final MissingCaseKind kind;
  final bool found;
  final LatLng point;
}

class MissingCaseContribution {
  MissingCaseContribution.fromJson(Map<String, dynamic> json)
    : id = json['id'] as int,
      author = json['author'] as String? ?? 'Usuario',
      message = json['message'] as String?,
      photoCount = json['photo_count'] as int,
      hasVideo = json['has_video'] == true,
      createdAt = DateTime.parse(json['created_at'] as String).toLocal();

  final int id;
  final String author;
  final String? message;
  final int photoCount;
  final bool hasVideo;
  final DateTime createdAt;
}
