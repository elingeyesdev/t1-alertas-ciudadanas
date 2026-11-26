import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'widgets/evidence_video.dart';

import '../core/constants/api_constants.dart';
import '../core/network/dio_client.dart';
import '../models/alert_model.dart';
import '../services/location_service.dart';
import '../services/user_service.dart';
import '../viewmodels/auth_viewmodel.dart';
import 'comments_screen.dart';

Future<XFile?> pickEvidence(BuildContext context, {required bool video}) async {
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    builder: (context) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            leading: const Icon(Icons.camera_alt),
            title: const Text('Cámara'),
            onTap: () => Navigator.pop(context, ImageSource.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library),
            title: const Text('Galería'),
            onTap: () => Navigator.pop(context, ImageSource.gallery),
          ),
        ],
      ),
    ),
  );
  if (source == null) return null;
  final picker = ImagePicker();
  return video
      ? picker.pickVideo(source: source)
      : picker.pickImage(source: source, imageQuality: 85);
}

class AlertDetailScreen extends StatefulWidget {
  const AlertDetailScreen({
    super.key,
    required this.reportId,
    this.pendingVideo,
    this.actions,
  });

  final int reportId;
  final XFile? pendingVideo;
  final Future<void> Function()? actions;

  @override
  State<AlertDetailScreen> createState() => _AlertDetailScreenState();
}

class _AlertDetailScreenState extends State<AlertDetailScreen> {
  Map<String, dynamic>? _detail;
  bool _loadFailed = false;
  bool _guideChecked = false;
  bool _updatingVote = false;

  Future<void> _updateVote({String? vote}) async {
    if (_updatingVote) return;
    final userId = context.read<AuthViewModel>().user?.id;
    setState(() => _updatingVote = true);
    try {
      if (vote == null && userId != null) {
        final location = await LocationService().getCurrentLocation();
        await UserService().updateLocation(
          userId,
          latitude: location.latitude,
          longitude: location.longitude,
        );
      } else if (vote != null) {
        final response = await dioClient.dio.post(
          '/reports/${widget.reportId}/votes',
          data: {'vote': vote},
        );
        if (mounted) setState(() => _detail?['voting'] = response.data);
      }
      if (mounted) await _load();
    } catch (error) {
      if (!mounted) return;
      if (error is DioException &&
          [403, 409].contains(error.response?.statusCode)) {
        await _load();
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            vote == null
                ? 'No se pudo actualizar tu ubicación. Revisa los permisos e inténtalo de nuevo.'
                : 'No se pudo registrar el voto. Revisa el estado del reporte e inténtalo de nuevo.',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _updatingVote = false);
    }
  }

  Future<void> _showVoting() async {
    setState(() => _covered = true);
    try {
      await showDialog<void>(
        context: context,
        builder: (_) => StatefulBuilder(
          builder: (sheetContext, refreshSheet) {
            final voting = _detail?['voting'];
            if (voting is! Map) return const SizedBox.shrink();
            return AlertDialog(
              title: const Text('Validar reporte'),
              scrollable: true,
              contentPadding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
              content: SizedBox(
                width: 320,
                child: _votingControls(voting, ({String? vote}) async {
                  final update = _updateVote(vote: vote);
                  refreshSheet(() {});
                  await update;
                  if (sheetContext.mounted) refreshSheet(() {});
                }),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(sheetContext).pop(),
                  child: const Text('Cerrar'),
                ),
              ],
            );
          },
        ),
      );
    } finally {
      if (mounted) setState(() => _covered = false);
    }
  }

  Widget _evidenceAction({
    required String label,
    required IconData icon,
    required VoidCallback onPressed,
  }) {
    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(
        minimumSize: const Size(86, 60),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 28),
          const SizedBox(height: 3),
          Text(label, style: const TextStyle(fontSize: 11)),
        ],
      ),
    );
  }

  Widget _votingControls(
    Map voting,
    Future<void> Function({String? vote}) updateVote,
  ) {
    final own = voting['own_vote'];
    final reason = voting['reason'];
    final canVote = voting['can_vote'] == true;
    return Material(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              own == null
                  ? 'Votación del reporte'
                  : canVote
                  ? 'Tu voto · Toca la otra opción para cambiarlo'
                  : 'Tu voto',
              style: Theme.of(context).textTheme.labelLarge,
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final (value, label) in [('seen', 'Lo vi'), ('false', 'Es falso')])
                  ChoiceChip(
                    label: Text('$label · ${voting[value]}'),
                    selected: own == value,
                    onSelected: canVote && !_updatingVote && own != value
                        ? (_) => updateVote(vote: value)
                        : null,
                    disabledColor: own == value
                        ? Theme.of(context).colorScheme.secondaryContainer
                        : null,
                  ),
              ],
            ),
            if (_updatingVote) const LinearProgressIndicator(),
              if (reason == 'missing_location' || reason == 'outside_radius')
                TextButton(
                  onPressed: _updatingVote ? null : () => updateVote(),
                  child: Text(
                    reason == 'missing_location'
                        ? 'Actualiza tu ubicación para votar'
                        : 'Solo vecinos cercanos · Actualizar ubicación',
                  ),
                ),
          ],
        ),
      ),
    );
  }

  int _page = 0;
  XFile? _pending;
  bool _video = true;
  bool _uploading = false;
  bool _covered = false;
  bool _picking = false;
  double? _progress;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
    _pending = widget.pendingVideo;
    if (_pending != null) _upload();
  }

  Future<void> _load() async {
    try {
      final response = await dioClient.dio.get('/reports/${widget.reportId}');
      if (!mounted) return;
      setState(() {
        _detail = Map<String, dynamic>.from(response.data as Map);
        _loadFailed = false;
      });
      if (!_guideChecked) {
        _guideChecked = true;
        await _showFirstReportGuide();
      }
    } catch (_) {
      if (mounted) setState(() => _loadFailed = true);
    }
  }

  Future<void> _showFirstReportGuide() async {
    final userId = context.read<AuthViewModel>().user?.id;
    if (userId == null) return;
    final prefs = await SharedPreferences.getInstance();
    final key = 'report_evidence_guide_v1_$userId';
    if (!mounted || prefs.getBool(key) == true) return;

    setState(() => _covered = true);
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _EvidenceGuide(),
    );
    await prefs.setBool(key, true);
    if (mounted) setState(() => _covered = false);
  }

  Future<void> _showComments() async {
    setState(() => _covered = true);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      clipBehavior: Clip.antiAlias,
      builder: (context) => FractionallySizedBox(
        heightFactor: 0.75,
        child: CommentsScreen(alertId: widget.reportId),
      ),
    );
    if (!mounted) return;
    setState(() => _covered = false);
    await _load();
  }

  Future<void> _pick(bool video) async {
    setState(() {
      _covered = true;
      _picking = true;
    });
    try {
      final file = await pickEvidence(context, video: video);
      if (!mounted || file == null) return;
      _pending = file;
      _video = video;
      await _upload();
    } catch (_) {
      if (mounted)
        setState(() => _error = 'No se pudo abrir la cámara o galería.');
    } finally {
      if (mounted)
        setState(() {
          _covered = false;
          _picking = false;
        });
    }
  }

  Future<void> _upload() async {
    final file = _pending;
    if (file == null || _uploading) return;
    setState(() {
      _uploading = true;
      _progress = null;
      _error = null;
    });
    final field = _video ? 'video' : 'image';
    final endpoint = _video ? 'videos' : 'images';
    try {
      final maxMiB = _video ? 20 : 10;
      if (await file.length() > maxMiB * 1024 * 1024) {
        if (mounted)
          setState(() => _error = 'El archivo supera los $maxMiB MB.');
        return;
      }
      await dioClient.dio.post(
        '/reports/${widget.reportId}/$endpoint',
        data: FormData.fromMap({
          field: await MultipartFile.fromFile(file.path, filename: file.name),
        }),
        onSendProgress: (sent, total) {
          if (mounted && total > 0) setState(() => _progress = sent / total);
        },
      );
      _pending = null;
      await _load();
    } catch (error) {
      if (mounted)
        setState(() {
          _error =
              error is DioException &&
                  (error.response?.statusCode == 400 ||
                      error.response?.statusCode == 422)
              ? 'Archivo inválido. Usa un video MP4 de hasta 20 MB o una foto de hasta 10 MB.'
              : 'No se pudo subir la evidencia.';
        });
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _open(BuildContext context, WidgetBuilder builder) async {
    setState(() => _covered = true);
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: builder));
    if (!mounted) return;
    setState(() => _covered = false);
    await _load();
  }

  String _label(Object? value) =>
      (value ?? 'Sin datos').toString().replaceAll('_', ' ');

  Widget _info(
    BuildContext context,
    AlertModel report,
    List<dynamic> assignments,
  ) {
    final date = report.createdAt;
    return Scaffold(
      appBar: AppBar(title: const Text('Datos de la alerta')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(report.type, style: Theme.of(context).textTheme.headlineSmall),
          Text(report.description),
          ListTile(
            title: const Text('Estado'),
            subtitle: Text(_label(_detail?['status'])),
          ),
          ListTile(
            title: const Text('Verificación'),
            subtitle: Text(report.verified ? 'Verificada' : 'Pendiente'),
          ),
          ListTile(
            title: const Text('Ubicación'),
            subtitle: Text(
              [
                report.zone ?? 'Sin zona',
                if (report.coordinates.length >= 2)
                  '${report.coordinates[1]}, ${report.coordinates[0]}',
              ].join('\n'),
            ),
          ),
          if (date != null)
            ListTile(
              title: const Text('Fecha'),
              subtitle: Text(
                MaterialLocalizations.of(
                  context,
                ).formatFullDate(date.toLocal()),
              ),
            ),
          const ListTile(title: Text('Autoridades asignadas')),
          if (assignments.isEmpty)
            const ListTile(subtitle: Text('Sin autoridades asignadas.')),
          for (final assignment in assignments)
            ListTile(
              leading: const Icon(Icons.local_police_outlined),
              title: Text(_label(assignment['type'])),
              subtitle: Text(
                [
                  if (assignment['unit'] != null) assignment['unit'],
                  _label(assignment['state']),
                ].join(' · '),
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    final voting = detail?['voting'];
    final report = detail == null ? null : AlertModel.fromJson(detail);
    final items = detail?['evidence'] as List<dynamic>? ?? [];
    final evidence = [
      ...items.where((item) => item['type'] == 'video'),
      ...items.where((item) => item['type'] != 'video'),
    ];
    final assignments = detail?['assignments'] as List<dynamic>? ?? [];
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: Text(report?.type ?? 'Alerta'),
        actions: [
          if (widget.actions != null)
            IconButton(
              tooltip: 'Acciones de la alerta',
              onPressed: () async {
                setState(() => _covered = true);
                await widget.actions!();
                if (!mounted) return;
                setState(() => _covered = false);
                await _load();
              },
              icon: const Icon(Icons.more_horiz),
            ),
          PopupMenuButton<bool>(
            tooltip: 'Aportar evidencia',
            icon: const Icon(Icons.add_circle_outline),
            enabled:
                !_uploading && !_picking && _pending == null && report != null,
            onSelected: _pick,
            itemBuilder: (_) => const [
              PopupMenuItem(value: false, child: Text('Aportar foto')),
              PopupMenuItem(value: true, child: Text('Aportar video')),
            ],
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            if (_uploading) LinearProgressIndicator(value: _progress),
            if (_error != null)
              Material(
                child: ListTile(
                  title: Text(_error!),
                  trailing: _pending == null
                      ? null
                      : Wrap(
                          children: [
                            TextButton(
                              onPressed: _upload,
                              child: const Text('Reintentar'),
                            ),
                            IconButton(
                              tooltip: 'Descartar archivo',
                              onPressed: () => setState(() {
                                _pending = null;
                                _error = null;
                              }),
                              icon: const Icon(Icons.close),
                            ),
                          ],
                        ),
                ),
              ),
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  _loadFailed
                      ? Center(
                          child: TextButton(
                            onPressed: _load,
                            child: const Text('Reintentar carga de la alerta'),
                          ),
                        )
                      : report == null
                      ? const Center(child: CircularProgressIndicator())
                      : evidence.isEmpty
                      ? const Center(
                          child: Text(
                            'Sin evidencias',
                            style: TextStyle(color: Colors.white),
                          ),
                        )
                      : PageView.builder(
                          scrollDirection: Axis.vertical,
                          reverse: true,
                          itemCount: evidence.length,
                          onPageChanged: (index) =>
                              setState(() => _page = index),
                          itemBuilder: (context, index) {
                            final item = evidence[index];
                            final uri = Uri.parse(
                              ApiConstants.apiBaseUrl,
                            ).resolve(item['url'].toString());
                            return item['type'] == 'video'
                                ? EvidenceVideo(
                                    key: ValueKey(uri),
                                    uri: uri,
                                    active: index == _page && !_covered,
                                  )
                                : FractionallySizedBox(
                                  heightFactor: 0.92,
                                  child: Image.network(
                                    uri.toString(),
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, _, _) => const Center(
                                      child: Text(
                                        'No se pudo cargar la foto',
                                        style: TextStyle(color: Colors.white),
                                      ),
                                    ),
                                  ),
                                );
                          },
                        ),
                  if (report != null)
                    Positioned(
                      right: 12,
                      bottom: 16,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (voting is Map) ...[
                            _evidenceAction(
                              label: 'Votación',
                              onPressed: _showVoting,
                              icon: voting['own_vote'] == null
                                    ? Icons.how_to_vote_outlined
                                    : Icons.how_to_vote,
                            ),
                            const SizedBox(height: 10),
                          ],
                          _evidenceAction(
                            label: 'Comentarios',
                            onPressed: _showComments,
                            icon: Icons.comment_outlined,
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            if (report != null)
              Material(
                child: ListTile(
                  title: Text('Estado · ${_label(detail?['status'])}'),
                  subtitle: Text(
                    report.description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  onTap: () => _open(
                    context,
                    (context) => _info(context, report, assignments),
                  ),
                  trailing: IconButton(
                    tooltip: 'Datos y autoridades asignadas',
                    onPressed: () => _open(
                      context,
                      (context) => _info(context, report, assignments),
                    ),
                    icon: const Icon(Icons.info_outline),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _EvidenceGuide extends StatelessWidget {
  const _EvidenceGuide();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    const steps = [
      (
        Icons.south_rounded,
        'Desliza hacia abajo',
        'Ve primero los videos y luego las fotos.',
      ),
      (
        Icons.add_circle_outline,
        'Aporta evidencia',
        'Toca + para usar la cámara o la galería.',
      ),
      (
        Icons.comment_outlined,
        'Abre los comentarios',
        'Toca el botón del lado derecho para leer o comentar.',
      ),
    ];

    return SafeArea(
      top: false,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Explora la alerta', style: theme.textTheme.headlineSmall),
            const SizedBox(height: 20),
            for (final (icon, title, description) in steps)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: colors.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 6,
                    ),
                    leading: CircleAvatar(
                      backgroundColor: colors.primaryContainer,
                      foregroundColor: colors.onPrimaryContainer,
                      child: Icon(icon),
                    ),
                    title: Text(title, style: theme.textTheme.titleMedium),
                    subtitle: Text(description),
                  ),
                ),
              ),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Ver la alerta'),
            ),
          ],
        ),
      ),
    );
  }
}
