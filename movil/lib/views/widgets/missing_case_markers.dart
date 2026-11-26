import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';

import '../../models/missing_case.dart';
import '../missing_cases_screen.dart';

List<Marker> missingCaseMarkers(
  BuildContext context,
  List<MissingCasePin> pins,
) {
  final groups = <String, List<MissingCasePin>>{};
  for (final pin in pins) {
    final point = pin.point;
    final key =
        '${point.latitude.toStringAsFixed(6)},${point.longitude.toStringAsFixed(6)}';
    (groups[key] ??= []).add(pin);
  }

  return groups.values.map((group) {
    final first = group.first;
    final label = group.length > 1
        ? '${group.length} casos de desaparición'
        : '${first.kind.label}: ${first.name}${first.found ? ' · Encontrado' : ''}';
    return Marker(
      key: ValueKey(first.id),
      point: first.point,
      width: 44,
      height: 44,
      child: IconButton.filled(
        tooltip: label,
        style: IconButton.styleFrom(
          backgroundColor: group.every((pin) => pin.found)
              ? const Color(0xFF2D6949)
              : const Color(0xFF006968),
          foregroundColor: Colors.white,
          side: const BorderSide(color: Colors.white, width: 1.5),
        ),
        icon: Icon(
          group.length > 1
              ? Icons.layers_outlined
              : first.kind == MissingCaseKind.pet
              ? Icons.pets
              : Icons.person_search_outlined,
        ),
        onPressed: () => _openCase(context, group),
      ),
    );
  }).toList();
}

Future<void> _openCase(BuildContext context, List<MissingCasePin> group) async {
  final id = group.length == 1
      ? group.first.id
      : await showModalBottomSheet<int>(
          context: context,
          showDragHandle: true,
          builder: (sheetContext) => SafeArea(
            child: ListView(
              shrinkWrap: true,
              children: [
                const ListTile(title: Text('Casos en este lugar')),
                for (final item in group)
                  ListTile(
                    title: Text(item.name),
                    subtitle: Text(
                      item.found
                          ? '${item.kind.label} · Encontrado'
                          : item.kind.label,
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(sheetContext).pop(item.id),
                  ),
              ],
            ),
          ),
        );
  if (id == null || !context.mounted) return;
  await Navigator.of(context).push<void>(
    MaterialPageRoute(builder: (_) => MissingCaseDetailScreen(caseId: id)),
  );
}
