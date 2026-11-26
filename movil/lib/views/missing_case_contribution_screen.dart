import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/missing_case_service.dart';
import 'alert_detail_screen.dart' show pickEvidence;
import 'widgets/missing_case_photo_picker.dart';

class MissingCaseContributionScreen extends StatefulWidget {
  const MissingCaseContributionScreen({
    super.key,
    required this.caseId,
    this.pickVideoOnOpen = false,
  });

  final int caseId;
  final bool pickVideoOnOpen;

  @override
  State<MissingCaseContributionScreen> createState() =>
      _MissingCaseContributionScreenState();
}

class _MissingCaseContributionScreenState
    extends State<MissingCaseContributionScreen> {
  final _message = TextEditingController();
  List<XFile> _photos = [];
  XFile? _video;
  bool _pickingVideo = false;
  bool _sending = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.pickVideoOnOpen) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _pickVideo();
      });
    }
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final message = _message.text.trim();
    if (message.isEmpty && _photos.isEmpty && _video == null) {
      setState(
        () => _error = 'Escribe un comentario o adjunta una foto o un video.',
      );
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      var totalBytes = await _video?.length() ?? 0;
      for (final photo in _photos) {
        totalBytes += await photo.length();
      }
      if (totalBytes > 20 * 1024 * 1024) {
        if (mounted)
          setState(
            () => _error = 'Los archivos del aporte pueden sumar hasta 20 MB.',
          );
        return;
      }
      await MissingCaseService().contribute(
        widget.caseId,
        message,
        _photos,
        video: _video,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on DioException catch (error) {
      if (!mounted) return;
      final data = error.response?.data;
      final message = data is Map ? data['message'] : null;
      setState(() {
        _error = switch (error.response?.statusCode) {
          409 => 'El caso ya está cerrado.',
          429 => 'Espera un momento antes de publicar otro aporte.',
          400 when message is List && message.isNotEmpty =>
            message.first.toString(),
          _ => 'No se pudo publicar el aporte. Intenta de nuevo.',
        };
      });
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'No se pudieron adjuntar las fotos.');
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _pickVideo() async {
    setState(() {
      _pickingVideo = true;
      _error = null;
    });
    try {
      final video = await pickEvidence(context, video: true);
      if (!mounted || video == null) return;
      final length = await video.length();
      if (!mounted) return;
      if (!video.name.toLowerCase().endsWith('.mp4') ||
          length > 20 * 1024 * 1024) {
        setState(() => _error = 'Elige un video MP4 de hasta 20 MB.');
        return;
      }
      setState(() => _video = video);
    } catch (_) {
      if (mounted) setState(() => _error = 'No se pudo abrir el video.');
    } finally {
      if (mounted) setState(() => _pickingVideo = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Aportar información')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          TextField(
            controller: _message,
            enabled: !_sending,
            minLines: 3,
            maxLines: 6,
            maxLength: 2000,
            decoration: const InputDecoration(
              labelText: 'Comentario',
              hintText: 'Describe lo que viste y dónde.',
            ),
          ),
          const SizedBox(height: 12),
          MissingCasePhotoPicker(
            photos: _photos,
            onChanged: (photos) => setState(() {
              _photos = photos;
              _error = null;
            }),
            enabled: !_sending && !_pickingVideo,
          ),
          if (_video case final video?)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.videocam_outlined),
              title: Text(video.name, overflow: TextOverflow.ellipsis),
              trailing: IconButton(
                tooltip: 'Quitar video',
                icon: const Icon(Icons.close),
                onPressed: _sending || _pickingVideo
                    ? null
                    : () => setState(() => _video = null),
              ),
            ),
          OutlinedButton.icon(
            onPressed: _sending || _pickingVideo ? null : _pickVideo,
            icon: const Icon(Icons.video_library_outlined),
            label: Text(_video == null ? 'Adjuntar video' : 'Cambiar video'),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _sending || _pickingVideo ? null : _send,
            child: Text(_sending ? 'Publicando…' : 'Publicar aporte'),
          ),
        ],
      ),
    ),
  );
}
