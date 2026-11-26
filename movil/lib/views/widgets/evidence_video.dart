import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

class EvidenceVideo extends StatefulWidget {
  const EvidenceVideo({
    super.key,
    required this.uri,
    required this.active,
    this.headers = const {},
  });
  final Uri uri;
  final bool active;
  final Map<String, String> headers;

  @override
  State<EvidenceVideo> createState() => _EvidenceVideoState();
}

class _EvidenceVideoState extends State<EvidenceVideo> {
  late final VideoPlayerController _controller;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _controller = VideoPlayerController.networkUrl(
      widget.uri,
      httpHeaders: widget.headers,
    );
    _initialize();
  }

  Future<void> _initialize() async {
    try {
      await _controller.initialize();
      if (!mounted) return;
      setState(() {});
      if (widget.active) await _controller.play();
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    }
  }

  @override
  void didUpdateWidget(covariant EvidenceVideo oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_controller.value.isInitialized && oldWidget.active != widget.active) {
      if (widget.active) {
        _controller.play();
      } else {
        _controller.pause();
      }
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Center(
    child: _failed
        ? const Text(
            'No se pudo reproducir el video',
            style: TextStyle(color: Colors.white),
          )
        : !_controller.value.isInitialized
        ? const CircularProgressIndicator()
        : AspectRatio(
            aspectRatio: _controller.value.aspectRatio,
            child: GestureDetector(
              onTap: () {
                if (_controller.value.isPlaying) {
                  _controller.pause();
                } else {
                  _controller.play();
                }
              },
              child: VideoPlayer(_controller),
            ),
          ),
  );
}
