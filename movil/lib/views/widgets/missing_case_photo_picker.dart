import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

class MissingCasePhotoPicker extends StatefulWidget {
  const MissingCasePhotoPicker({
    super.key,
    required this.photos,
    required this.onChanged,
    required this.enabled,
  });

  final List<XFile> photos;
  final ValueChanged<List<XFile>> onChanged;
  final bool enabled;

  @override
  State<MissingCasePhotoPicker> createState() => _MissingCasePhotoPickerState();
}

class _MissingCasePhotoPickerState extends State<MissingCasePhotoPicker> {
  final _picker = ImagePicker();
  String? _error;
  bool _picking = false;

  Future<void> _pick() async {
    setState(() {
      _picking = true;
      _error = null;
    });
    try {
      final picks = await _picker.pickMultiImage(
        maxWidth: 1800,
        imageQuality: 85,
      );
      if (!mounted || picks.isEmpty) return;
      if (widget.photos.length + picks.length > 3) {
        setState(() => _error = 'Puedes adjuntar hasta tres fotos.');
        return;
      }
      for (final photo in picks) {
        if (await photo.length() > 5 * 1024 * 1024) {
          if (mounted) setState(() => _error = 'Cada foto admite hasta 5 MB.');
          return;
        }
      }
      if (mounted && widget.enabled) {
        widget.onChanged([...widget.photos, ...picks]);
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'No se pudieron abrir las fotos.');
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final photo in widget.photos)
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.photo_outlined),
          title: Text(photo.name, overflow: TextOverflow.ellipsis),
          trailing: IconButton(
            tooltip: 'Quitar foto',
            icon: const Icon(Icons.close),
            onPressed: widget.enabled && !_picking
                ? () {
                    setState(() => _error = null);
                    widget.onChanged(
                      widget.photos.where((item) => item != photo).toList(),
                    );
                  }
                : null,
          ),
        ),
      OutlinedButton.icon(
        onPressed: widget.enabled && !_picking && widget.photos.length < 3
            ? _pick
            : null,
        icon: const Icon(Icons.add_photo_alternate_outlined),
        label: Text('Adjuntar fotos (${widget.photos.length}/3)'),
      ),
      if (_error != null)
        Text(
          _error!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
    ],
  );
}
