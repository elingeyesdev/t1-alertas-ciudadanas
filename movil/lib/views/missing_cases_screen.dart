import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/missing_case.dart';
import '../services/missing_case_service.dart';
import 'missing_case_contribution_screen.dart';
import 'missing_case_suggestion_screen.dart';
import 'widgets/evidence_video.dart';

class MissingCasesScreen extends StatefulWidget {
  const MissingCasesScreen({super.key, required this.canSuggest});

  final bool canSuggest;

  @override
  State<MissingCasesScreen> createState() => _MissingCasesScreenState();
}

class _MissingCasesScreenState extends State<MissingCasesScreen> {
  int _mineVersion = 0;

  Future<void> _suggest(BuildContext context) async {
    final submitted = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const MissingCaseSuggestionScreen()),
    );
    if (submitted == true && mounted && context.mounted) {
      setState(() => _mineVersion++);
      DefaultTabController.of(context).animateTo(MissingCaseKind.values.length);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Sugerencia enviada. Pendiente de revisión.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: MissingCaseKind.values.length + 1,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Desaparecidos'),
        bottom: TabBar(
          tabs: [
            for (final kind in MissingCaseKind.values) Tab(text: kind.plural),
            const Tab(text: 'Mis casos'),
          ],
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (widget.canSuggest)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: SizedBox(
                  width: double.infinity,
                  child: Builder(
                    builder: (context) => OutlinedButton.icon(
                      onPressed: () => _suggest(context),
                      icon: const Icon(Icons.add),
                      label: const Text('Sugerir un caso'),
                    ),
                  ),
                ),
              ),
            Expanded(
              child: TabBarView(
                children: [
                  const _CaseList(kind: MissingCaseKind.person),
                  const _CaseList(kind: MissingCaseKind.pet),
                  _CaseList(key: ValueKey(_mineVersion)),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _CaseList extends StatefulWidget {
  const _CaseList({super.key, this.kind});

  final MissingCaseKind? kind;

  @override
  State<_CaseList> createState() => _CaseListState();
}

class _CaseListState extends State<_CaseList> {
  final _service = MissingCaseService();
  final _cases = <MissingCase>[];
  bool _loading = false;
  bool _failed = false;
  bool _hasMore = false;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _load(refresh: true);
  }

  Future<void> _load({bool refresh = false}) async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _failed = false;
    });
    final page = refresh ? 1 : _page + 1;
    try {
      final result = await _service.list(widget.kind, page);
      if (!mounted) return;
      setState(() {
        _page = page;
        _hasMore = result.hasMore;
        if (refresh) _cases.clear();
        final currentIds = _cases.map((item) => item.id).toSet();
        _cases.addAll(result.cases.where((item) => currentIds.add(item.id)));
      });
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => _load(refresh: true),
    child: ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: _cases.length + 1,
      itemBuilder: (context, index) {
        if (index == _cases.length) {
          return _buildListFooter();
        }
        final item = _cases[index];
        return Padding(
          key: ValueKey(item.id),
          padding: const EdgeInsets.only(bottom: 12),
          child: _CaseCard(
            item: item,
            showStatus: widget.kind == null,
            openDetails: () async {
              await Navigator.of(context).push<void>(
                MaterialPageRoute(
                  builder: (_) => MissingCaseDetailScreen(caseId: item.id),
                ),
              );
              if (mounted) await _load(refresh: true);
            },
          ),
        );
      },
    ),
  );

  Widget _buildListFooter() {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (_failed) {
      return _LoadError(onRetry: () => _load(refresh: true));
    }
    if (_cases.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 24),
        child: Text(
          widget.kind == null
              ? 'Todavía no has sugerido casos.'
              : 'Todavía no hay casos publicados.',
          textAlign: TextAlign.center,
        ),
      );
    }
    if (_hasMore) {
      return Center(
        child: TextButton(
          onPressed: () => _load(),
          child: const Text('Ver más casos'),
        ),
      );
    }
    return const SizedBox.shrink();
  }
}

class MissingCaseDetailScreen extends StatefulWidget {
  const MissingCaseDetailScreen({super.key, required this.caseId});

  final int caseId;

  @override
  State<MissingCaseDetailScreen> createState() =>
      _MissingCaseDetailScreenState();
}

class _MissingCaseDetailScreenState extends State<MissingCaseDetailScreen> {
  final _service = MissingCaseService();
  final _pager = PageController();
  late Future<MissingCase> _case = _service.get(widget.caseId);
  late final _videoHeaders = _service.videoHeaders();
  bool _covered = false;
  int _page = 0;

  @override
  void dispose() {
    _pager.dispose();
    super.dispose();
  }

  void _reload({bool startAtFirst = false}) {
    if (startAtFirst && _pager.hasClients) _pager.jumpToPage(0);
    setState(() {
      if (startAtFirst) _page = 0;
      _case = _service.get(widget.caseId);
    });
  }

  Future<void> _openSheet(Widget content, {double? heightFactor}) async {
    setState(() => _covered = true);
    try {
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        showDragHandle: true,
        builder: (_) => heightFactor == null
            ? content
            : FractionallySizedBox(heightFactor: heightFactor, child: content),
      );
    } finally {
      if (mounted) setState(() => _covered = false);
    }
  }

  Future<void> _contribute(bool pickVideo) async {
    setState(() => _covered = true);
    try {
      final sent = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => MissingCaseContributionScreen(
            caseId: widget.caseId,
            pickVideoOnOpen: pickVideo,
          ),
        ),
      );
      if (sent == true && mounted) _reload(startAtFirst: true);
    } finally {
      if (mounted) setState(() => _covered = false);
    }
  }

  void _showComments(MissingCase item) => _openSheet(
    CustomScrollView(
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          sliver: _Contributions(
            caseId: item.id,
            closed: item.found,
            onContributionAdded: () => _reload(startAtFirst: true),
          ),
        ),
      ],
    ),
    heightFactor: 0.75,
  );

  void _showDetails(MissingCase item) => _openSheet(
    SafeArea(
      top: false,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Detalles de la desaparición',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            Text('${item.kind.label} · ${item.status.label}'),
            const SizedBox(height: 8),
            Text('Lugar: ${item.place}'),
            Text(
              'Fecha de desaparición: ${DateFormat('dd/MM/yyyy').format(item.missingOn)}',
            ),
            const SizedBox(height: 12),
            Text(item.description),
            if (item.referencePhone case final phone?) ...[
              const SizedBox(height: 12),
              Text('Teléfono de referencia: $phone'),
              TextButton.icon(
                onPressed: () => _callPhone(context, phone),
                icon: const Icon(Icons.call_outlined),
                label: const Text('Llamar'),
              ),
            ],
          ],
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) => FutureBuilder<MissingCase>(
    future: _case,
    builder: (context, snapshot) {
      final item = snapshot.data;
      final open = item?.status == MissingCaseStatus.published;
      return Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          title: Text(item?.name ?? 'Desaparecidos'),
          actions: [
            if (open)
              PopupMenuButton<bool>(
                tooltip: 'Aportar información',
                icon: const Icon(Icons.add_circle_outline),
                onSelected: _contribute,
                itemBuilder: (_) => const [
                  PopupMenuItem(
                    value: false,
                    child: Text('Aportar información'),
                  ),
                  PopupMenuItem(value: true, child: Text('Aportar video')),
                ],
              ),
            IconButton(
              tooltip: 'Actualizar caso',
              onPressed: snapshot.connectionState == ConnectionState.done
                  ? _reload
                  : null,
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        body: SafeArea(
          child: item == null
              ? Center(
                  child: snapshot.hasError
                      ? TextButton(
                          onPressed: _reload,
                          child: const Text('Reintentar carga del caso'),
                        )
                      : const CircularProgressIndicator(),
                )
              : _detail(item, ModalRoute.isCurrentOf(context) ?? true),
        ),
      );
    },
  );

  Widget _detail(MissingCase item, bool visible) {
    final evidence = [
      ...item.evidence.where((media) => media.isVideo),
      ...item.evidence.where((media) => !media.isVideo),
    ];
    final phone = item.referencePhone;
    return Column(
      children: [
        Expanded(
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (evidence.isEmpty)
                const Center(
                  child: Text(
                    'Sin fotos ni videos',
                    style: TextStyle(color: Colors.white),
                  ),
                )
              else
                PageView.builder(
                  controller: _pager,
                  scrollDirection: Axis.vertical,
                  reverse: true,
                  itemCount: evidence.length,
                  onPageChanged: (index) => setState(() => _page = index),
                  itemBuilder: (context, index) {
                    final media = evidence[index];
                    return media.isVideo
                        ? FutureBuilder<Map<String, String>>(
                            key: ValueKey(media.key),
                            future: _videoHeaders,
                            builder: (context, snapshot) {
                              if (snapshot.hasError) {
                                return const Center(
                                  child: Text(
                                    'No se pudo cargar el video',
                                    style: TextStyle(color: Colors.white),
                                  ),
                                );
                              }
                              if (!snapshot.hasData)
                                return const Center(
                                  child: CircularProgressIndicator(),
                                );
                              return EvidenceVideo(
                                uri: _service.videoUri(
                                  item.id,
                                  media.contributionId!,
                                ),
                                headers: snapshot.requireData,
                                active: index == _page && visible && !_covered,
                              );
                            },
                          )
                        : FractionallySizedBox(
                            key: ValueKey(media.key),
                            heightFactor: 0.92,
                            child: _CasePhoto(
                              id: item.id,
                              index: media.index!,
                              contributionId: media.contributionId,
                              fullscreen: true,
                            ),
                          );
                  },
                ),
              if (evidence.length > 1)
                Positioned(
                  left: 12,
                  top: 12,
                  child: Chip(
                    label: Text(
                      '${(_page + 1).clamp(1, evidence.length)}/${evidence.length} · Desliza',
                    ),
                  ),
                ),
              if (item.status.isPublic)
                Positioned(
                  right: 12,
                  bottom: 16,
                  child: Column(
                    children: [
                      if (phone != null) ...[
                        _CaseAction(
                          label: 'Llamar',
                          icon: Icons.call_outlined,
                          onPressed: () => _callPhone(context, phone),
                        ),
                        const SizedBox(height: 10),
                      ],
                      _CaseAction(
                        label: 'Comentarios',
                        icon: Icons.comment_outlined,
                        onPressed: () => _showComments(item),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
        Material(
          child: ListTile(
            title: Text('Estado · ${item.status.label}'),
            subtitle: Text(
              item.description,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            trailing: IconButton(
              tooltip: 'Detalles de la desaparición',
              icon: const Icon(Icons.info_outline),
              onPressed: () => _showDetails(item),
            ),
            onTap: () => _showDetails(item),
          ),
        ),
      ],
    );
  }
}

class _CaseAction extends StatelessWidget {
  const _CaseAction({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => FilledButton(
    style: FilledButton.styleFrom(
      minimumSize: const Size(86, 60),
      padding: const EdgeInsets.all(8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
    ),
    onPressed: onPressed,
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

class _Contributions extends StatefulWidget {
  const _Contributions({
    required this.caseId,
    required this.closed,
    required this.onContributionAdded,
  });

  final int caseId;
  final bool closed;
  final VoidCallback onContributionAdded;

  @override
  State<_Contributions> createState() => _ContributionsState();
}

class _ContributionsState extends State<_Contributions> {
  final _service = MissingCaseService();
  final _items = <MissingCaseContribution>[];
  int _page = 0;
  bool _loading = false;
  bool _failed = false;
  bool _hasMore = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool refresh = false}) async {
    if (_loading) return;
    final page = refresh ? 1 : _page + 1;
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      final result = await _service.contributions(widget.caseId, page);
      if (!mounted) return;
      setState(() {
        _page = page;
        _hasMore = result.hasMore;
        if (refresh) _items.clear();
        final ids = _items.map((item) => item.id).toSet();
        _items.addAll(result.items.where((item) => ids.add(item.id)));
      });
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _contribute() async {
    final sent = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => MissingCaseContributionScreen(caseId: widget.caseId),
      ),
    );
    if (sent == true && mounted) {
      widget.onContributionAdded();
      await _load(refresh: true);
    }
  }

  @override
  Widget build(BuildContext context) => SliverList.builder(
    itemCount: _items.length + 2,
    itemBuilder: (context, index) {
      if (index == 0) return _header(context);
      if (index == _items.length + 1) return _footer();
      final item = _items[index - 1];
      return Card(
        key: ValueKey(item.id),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ListTile(
              title: Text(item.author),
              subtitle: Text(
                DateFormat('dd/MM/yyyy HH:mm').format(item.createdAt),
              ),
            ),
            if (item.message case final message?)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: Text(message),
              ),
            if (item.hasVideo)
              const ListTile(
                leading: Icon(Icons.videocam_outlined),
                title: Text('Video adjunto en la galería del caso'),
              ),
            for (var index = 0; index < item.photoCount; index++)
              _CasePhoto(
                id: widget.caseId,
                index: index,
                contributionId: item.id,
              ),
          ],
        ),
      );
    },
  );

  Widget _header(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(
        'Comentarios y aportes',
        style: Theme.of(context).textTheme.titleLarge,
      ),
      if (!widget.closed)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: OutlinedButton.icon(
            onPressed: _loading ? null : _contribute,
            icon: const Icon(Icons.add_comment_outlined),
            label: const Text('Aportar información'),
          ),
        ),
    ],
  );

  Widget _footer() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_failed) {
      return TextButton(
        onPressed: () => _load(refresh: true),
        child: const Text('Reintentar cargar aportes'),
      );
    }
    if (_items.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: Text('Todavía no hay aportes.'),
      );
    }
    if (_hasMore) {
      return TextButton(onPressed: _load, child: const Text('Ver más aportes'));
    }
    return const SizedBox.shrink();
  }
}

Future<void> _callPhone(BuildContext context, String phone) async {
  try {
    if (await launchUrl(Uri(scheme: 'tel', path: phone))) return;
  } catch (_) {
    // El número permanece disponible en los detalles.
  }
  if (context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('No se pudo abrir el marcador.')),
    );
  }
}

class _CaseCard extends StatelessWidget {
  const _CaseCard({
    required this.item,
    required this.openDetails,
    required this.showStatus,
  });

  final MissingCase item;
  final VoidCallback openDetails;
  final bool showStatus;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final phone = item.found ? null : item.referencePhone;
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (item.photoCount > 0) _CasePhoto(id: item.id, index: 0),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (showStatus || item.found)
                  Chip(
                    avatar: item.found
                        ? const Icon(Icons.check_circle_outline, size: 18)
                        : null,
                    label: Text(item.status.label),
                    backgroundColor: theme.colorScheme.secondaryContainer,
                  ),
                Text(item.name, style: theme.textTheme.titleLarge),
                const SizedBox(height: 8),
                Text('Lugar: ${item.place}'),
                Text(
                  'Fecha de desaparición: ${DateFormat('dd/MM/yyyy').format(item.missingOn)}',
                ),
                const SizedBox(height: 12),
                Text(item.description),
                if (phone != null) ...[
                  const SizedBox(height: 12),
                  Text('Teléfono de referencia: $phone'),
                  TextButton.icon(
                    onPressed: () => _callPhone(context, phone),
                    icon: const Icon(Icons.call_outlined),
                    label: const Text('Llamar'),
                  ),
                ],
                TextButton(
                  onPressed: openDetails,
                  child: const Text('Ver caso'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CasePhoto extends StatefulWidget {
  const _CasePhoto({
    required this.id,
    required this.index,
    this.contributionId,
    this.fullscreen = false,
  });

  final int id;
  final int index;
  final int? contributionId;
  final bool fullscreen;

  @override
  State<_CasePhoto> createState() => _CasePhotoState();
}

class _CasePhotoState extends State<_CasePhoto> {
  late final Future<Uint8List> _photo = MissingCaseService().photo(
    widget.id,
    widget.index,
    contributionId: widget.contributionId,
  );

  @override
  Widget build(BuildContext context) {
    final content = ColoredBox(
      color: widget.fullscreen
          ? Colors.black
          : Theme.of(context).colorScheme.surfaceContainerHighest,
      child: FutureBuilder<Uint8List>(
        future: _photo,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return _photoError();
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          return Image.memory(
            snapshot.requireData,
            fit: BoxFit.contain,
            cacheWidth: 1200,
            semanticLabel: widget.contributionId == null
                ? 'Foto ${widget.index + 1} del caso'
                : 'Foto ${widget.index + 1} del aporte',
            errorBuilder: (_, _, _) => _photoError(),
          );
        },
      ),
    );
    return widget.fullscreen
        ? content
        : AspectRatio(aspectRatio: 16 / 9, child: content);
  }

  Widget _photoError() => Center(
    child: Text(
      'Foto no disponible',
      style: widget.fullscreen ? const TextStyle(color: Colors.white) : null,
    ),
  );
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      const Text('No se pudieron cargar los casos.'),
      TextButton(onPressed: onRetry, child: const Text('Reintentar')),
    ],
  );
}
