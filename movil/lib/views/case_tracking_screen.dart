import 'dart:async';

import 'package:alertas_ciudadanas/core/utils/error_handler.dart';
import 'package:alertas_ciudadanas/models/case_tracking_model.dart';
import 'package:alertas_ciudadanas/services/case_service.dart';
import 'package:flutter/material.dart';

/// HU-26 Seguimiento del caso: el ciudadano ve en qué paso va su reporte
/// (recibido → verificado → unidad asignada → en camino → completado).
class CaseTrackingScreen extends StatefulWidget {
  final int reportId;

  const CaseTrackingScreen({super.key, required this.reportId});

  @override
  State<CaseTrackingScreen> createState() => _CaseTrackingScreenState();
}

class _CaseTrackingScreenState extends State<CaseTrackingScreen> {
  static const _accent = Color(0xFFAF6D58);
  static const _done = Color(0xFF6D8566);

  /// Pasos fijos que se muestran siempre, en orden.
  static const _steps = [
    ('creado', 'Reporte recibido', Icons.inbox_rounded),
    ('verificado', 'Verificado por una autoridad', Icons.verified_rounded),
    ('despachado', 'Unidad asignada', Icons.assignment_ind_rounded),
    ('en_camino', 'Unidad en camino', Icons.local_shipping_rounded),
    ('completado', 'Atención completada', Icons.check_circle_rounded),
  ];

  final _service = CaseService();
  CaseTrackingModel? _case;
  String? _error;
  bool _loading = true;
  Timer? _refresh;

  @override
  void initState() {
    super.initState();
    _load();
    // Mientras la pantalla está abierta se actualiza sola.
    _refresh = Timer.periodic(const Duration(seconds: 20), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _refresh?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) setState(() => _loading = true);
    try {
      final data = await _service.fetchCase(widget.reportId);
      if (!mounted) return;
      setState(() {
        _case = data;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        if (!silent || _case == null) _error = parseError(e);
        _loading = false;
      });
    }
  }

  /// Hasta qué paso llegó el caso (índice dentro de [_steps]).
  int _reachedIndex(CaseTrackingModel c) {
    if (c.stage == 'resuelto') return _steps.length - 1;
    final index = _steps.indexWhere((s) => s.$1 == c.stage);
    return index < 0 ? 0 : index;
  }

  String _time(DateTime? date) {
    if (date == null) return '';
    String two(int v) => v.toString().padLeft(2, '0');
    return '${two(date.day)}/${two(date.month)} ${two(date.hour)}:${two(date.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Seguimiento del reporte #${widget.reportId}')),
      body: RefreshIndicator(
        onRefresh: () => _load(silent: true),
        child: _buildBody(context),
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading && _case == null) {
      return const Center(child: CircularProgressIndicator());
    }
    final c = _case;
    if (c == null) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 80),
          const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          Text(_error ?? 'No se pudo cargar el seguimiento.', textAlign: TextAlign.center),
          const SizedBox(height: 12),
          Center(child: TextButton(onPressed: _load, child: const Text('Reintentar'))),
        ],
      );
    }

    final onSurface = Theme.of(context).colorScheme.onSurface;
    final reached = _reachedIndex(c);
    final active = c.activeDispatch;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        Text(c.type, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 4),
        Text('${c.zone} · ${_time(c.createdAt)}', style: TextStyle(color: onSurface.withValues(alpha: 0.6))),
        const SizedBox(height: 8),
        Text(c.description),
        if (c.stage == 'resuelto') ...[
          const SizedBox(height: 12),
          _banner(Icons.task_alt_rounded, 'Este caso fue marcado como resuelto.', _done),
        ],
        if (active != null) ...[
          const SizedBox(height: 16),
          _dispatchCard(active),
        ],
        const SizedBox(height: 24),
        const Text('Estado del caso', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        for (var i = 0; i < _steps.length; i++)
          _stepRow(
            icon: _steps[i].$3,
            title: _steps[i].$2,
            time: _time(c.timeOf(_steps[i].$1)),
            done: i <= reached,
            current: i == reached && c.stage != 'resuelto' && c.stage != 'completado',
            last: i == _steps.length - 1,
          ),
        const SizedBox(height: 24),
        const Text('Historial', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        for (final event in c.timeline.reversed)
          ListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            leading: Icon(
              event.type == 'cancelado' ? Icons.cancel_rounded : Icons.circle,
              size: event.type == 'cancelado' ? 20 : 10,
              color: event.type == 'cancelado' ? Colors.redAccent : _accent,
            ),
            title: Text(event.title, style: const TextStyle(fontWeight: FontWeight.w600)),
            subtitle: Text(event.detail),
            trailing: Text(_time(event.at), style: const TextStyle(fontSize: 12)),
          ),
      ],
    );
  }

  Widget _banner(IconData icon, String text, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 10),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }

  Widget _dispatchCard(CaseDispatchModel d) {
    final enRoute = d.departedAt != null;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _accent.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _accent.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: _accent,
            child: Icon(enRoute ? Icons.local_shipping_rounded : Icons.assignment_ind_rounded, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  enRoute ? '${d.unitKind} en camino' : '${d.unitKind} asignada',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                ),
                if (d.stationName != null) Text(d.stationName!),
                if (d.unitName.isNotEmpty) Text('A cargo: ${d.unitName}', style: const TextStyle(fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _stepRow({
    required IconData icon,
    required String title,
    required String time,
    required bool done,
    required bool current,
    required bool last,
  }) {
    final color = done ? (current ? _accent : _done) : Colors.grey.withValues(alpha: 0.5);
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 36,
            child: Column(
              children: [
                CircleAvatar(radius: 14, backgroundColor: color, child: Icon(icon, size: 16, color: Colors.white)),
                if (!last)
                  Expanded(child: Container(width: 2, color: done ? _done.withValues(alpha: 0.6) : Colors.grey.withValues(alpha: 0.3))),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 4, bottom: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontWeight: current ? FontWeight.bold : FontWeight.w500,
                      color: done ? null : Colors.grey,
                    ),
                  ),
                  if (current) const Text('Paso actual', style: TextStyle(fontSize: 12, color: _accent)),
                  if (time.isNotEmpty && done) Text(time, style: const TextStyle(fontSize: 12, color: Colors.grey)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
