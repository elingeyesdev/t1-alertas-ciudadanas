/// HU-26: seguimiento del caso (respuesta de GET /reports/{id}/case).
class CaseTrackingModel {
  final int reportId;
  final String type;
  final String description;
  final String zone;
  final String? status;
  final bool verified;
  final DateTime? createdAt;

  /// Paso actual: creado, verificado, despachado, en_camino, completado o resuelto.
  final String stage;
  final List<CaseEventModel> timeline;
  final List<CaseDispatchModel> dispatches;

  const CaseTrackingModel({
    required this.reportId,
    required this.type,
    required this.description,
    required this.zone,
    required this.status,
    required this.verified,
    required this.createdAt,
    required this.stage,
    required this.timeline,
    required this.dispatches,
  });

  factory CaseTrackingModel.fromJson(Map<String, dynamic> json) {
    final report = Map<String, dynamic>.from(json['report'] as Map? ?? const {});
    return CaseTrackingModel(
      reportId: (report['id'] as num?)?.toInt() ?? 0,
      type: (report['type'] ?? '').toString(),
      description: (report['description'] ?? '').toString(),
      zone: (report['zone'] ?? '').toString(),
      status: report['status']?.toString(),
      verified: report['verified'] == true,
      createdAt: _date(report['created_at']),
      stage: (json['stage'] ?? 'creado').toString(),
      timeline: (json['timeline'] as List? ?? const [])
          .whereType<Map>()
          .map((e) => CaseEventModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      dispatches: (json['dispatches'] as List? ?? const [])
          .whereType<Map>()
          .map((e) => CaseDispatchModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }

  /// Despacho que sigue en curso (el que el ciudadano está esperando), si hay.
  CaseDispatchModel? get activeDispatch {
    for (final d in dispatches) {
      if (d.state == 'en_curso') return d;
    }
    return null;
  }

  /// Hora en que ocurrió un paso de la línea de tiempo (la más reciente).
  DateTime? timeOf(String eventType) {
    DateTime? found;
    for (final e in timeline) {
      if (e.type == eventType && e.at != null) found = e.at;
    }
    return found;
  }
}

class CaseEventModel {
  final String type;
  final String title;
  final String detail;
  final DateTime? at;

  const CaseEventModel({required this.type, required this.title, required this.detail, required this.at});

  factory CaseEventModel.fromJson(Map<String, dynamic> json) => CaseEventModel(
        type: (json['type'] ?? '').toString(),
        title: (json['title'] ?? '').toString(),
        detail: (json['detail'] ?? '').toString(),
        at: _date(json['at']),
      );
}

class CaseDispatchModel {
  final int id;
  final String state;
  final DateTime? assignedAt;
  final DateTime? departedAt;
  final String unitName;
  final String? profileType;
  final String? stationName;

  const CaseDispatchModel({
    required this.id,
    required this.state,
    required this.assignedAt,
    required this.departedAt,
    required this.unitName,
    required this.profileType,
    required this.stationName,
  });

  factory CaseDispatchModel.fromJson(Map<String, dynamic> json) {
    final unit = Map<String, dynamic>.from(json['unit'] as Map? ?? const {});
    final station = json['station'] is Map ? Map<String, dynamic>.from(json['station'] as Map) : null;
    return CaseDispatchModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      state: (json['state'] ?? '').toString(),
      assignedAt: _date(json['assigned_at']),
      departedAt: _date(json['departed_at']),
      unitName: (unit['name'] ?? '').toString(),
      profileType: unit['profile_type']?.toString(),
      stationName: station?['name']?.toString(),
    );
  }

  String get unitKind {
    switch (profileType) {
      case 'paramedico':
        return 'Ambulancia';
      case 'bombero':
        return 'Bomberos';
      case 'policia':
        return 'Patrulla';
      default:
        return 'Unidad';
    }
  }
}

DateTime? _date(dynamic value) {
  if (value == null) return null;
  return DateTime.tryParse(value.toString())?.toLocal();
}
