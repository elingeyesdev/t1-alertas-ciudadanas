import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../core/config/mapbox_config.dart';
import '../models/missing_case.dart';
import '../services/missing_case_service.dart';
import '../viewmodels/tracking_provider.dart';
import 'missing_case_location_screen.dart';
import 'widgets/missing_case_photo_picker.dart';

class MissingCaseSuggestionScreen extends StatefulWidget {
  const MissingCaseSuggestionScreen({super.key});

  @override
  State<MissingCaseSuggestionScreen> createState() =>
      _MissingCaseSuggestionScreenState();
}

class _MissingCaseSuggestionScreenState
    extends State<MissingCaseSuggestionScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _place = TextEditingController();
  final _description = TextEditingController();
  final _phone = TextEditingController();
  final _service = MissingCaseService();
  List<XFile> _photos = [];
  MissingCaseKind _kind = MissingCaseKind.person;
  DateTime? _date;
  LatLng? _location;
  String? _error;
  bool _sending = false;

  @override
  void dispose() {
    _name.dispose();
    _place.dispose();
    _description.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _chooseLocation() async {
    final center =
        context.read<TrackingProvider>().currentLocation ??
        MapboxConfig.defaultCenter;
    final result = await Navigator.of(context).push<LatLng>(
      MaterialPageRoute(
        builder: (_) =>
            MissingCaseLocationScreen(center: center, selectedPoint: _location),
      ),
    );
    if (!mounted || result == null) return;
    setState(() {
      _location = result;
      _error = null;
    });
  }

  Future<void> _submit() async {
    if (_sending || !_form.currentState!.validate()) return;
    final location = _location;
    if (location == null) {
      setState(() => _error = 'Marca el lugar de desaparición en el mapa.');
      return;
    }
    if (_date == null || _photos.isEmpty) {
      setState(() => _error = 'Indica la fecha y adjunta al menos una foto.');
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await _service.suggest(
        kind: _kind,
        name: _name.text.trim(),
        place: _place.text.trim(),
        missingOn: DateFormat('yyyy-MM-dd').format(_date!),
        description: _description.text.trim(),
        phone: _phone.text.trim(),
        photos: _photos,
        location: location,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on DioException catch (error) {
      final data = error.response?.data;
      final message = data is Map ? data['message'] : null;
      if (mounted) {
        setState(() {
          _error = message is List && message.isNotEmpty
              ? message.first.toString()
              : 'No se pudo enviar la sugerencia. Intenta de nuevo.';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'No se pudieron adjuntar las fotos.');
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Widget _field(
    TextEditingController controller,
    String label, {
    required int maxLength,
    int maxLines = 1,
    TextInputType? keyboardType,
  }) => TextFormField(
    controller: controller,
    enabled: !_sending,
    maxLength: maxLength,
    maxLines: maxLines,
    keyboardType: keyboardType,
    decoration: InputDecoration(labelText: label),
    validator: (value) =>
        value == null || value.trim().isEmpty ? 'Completa este campo.' : null,
  );

  @override
  Widget build(BuildContext context) {
    final date = _date;
    return Scaffold(
      appBar: AppBar(title: const Text('Sugerir un caso')),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              DropdownButtonFormField<MissingCaseKind>(
                initialValue: _kind,
                decoration: const InputDecoration(labelText: 'Tipo de caso'),
                items: [
                  for (final kind in MissingCaseKind.values)
                    DropdownMenuItem(value: kind, child: Text(kind.label)),
                ],
                onChanged: _sending
                    ? null
                    : (kind) => setState(() => _kind = kind!),
              ),
              const SizedBox(height: 12),
              _field(_name, 'Nombre', maxLength: 120),
              _field(_place, 'Lugar de desaparición', maxLength: 180),
              OutlinedButton.icon(
                icon: Icon(
                  _location == null
                      ? Icons.add_location_alt_outlined
                      : Icons.check_circle_outline,
                ),
                label: Text(
                  _location == null
                      ? 'Elegir ubicación en el mapa'
                      : 'Ubicación marcada · Cambiar',
                ),
                onPressed: _sending ? null : _chooseLocation,
              ),
              OutlinedButton.icon(
                icon: const Icon(Icons.calendar_today_outlined),
                label: Text(
                  date == null
                      ? 'Fecha de desaparición'
                      : DateFormat('dd/MM/yyyy').format(date),
                ),
                onPressed: _sending
                    ? null
                    : () async {
                        final today = DateTime.now();
                        final chosen = await showDatePicker(
                          context: context,
                          initialDate: date ?? today,
                          firstDate: DateTime(1900),
                          lastDate: today,
                        );
                        if (mounted && chosen != null) {
                          setState(() => _date = chosen);
                        }
                      },
              ),
              _field(_description, 'Descripción', maxLength: 2000, maxLines: 4),
              _field(
                _phone,
                'Teléfono de referencia',
                maxLength: 22,
                keyboardType: TextInputType.phone,
              ),
              const Text('El teléfono se mostrará si se aprueba el caso.'),
              const SizedBox(height: 16),
              MissingCasePhotoPicker(
                photos: _photos,
                onChanged: (photos) => setState(() {
                  _photos = photos;
                  _error = null;
                }),
                enabled: !_sending,
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _sending ? null : _submit,
                child: Text(_sending ? 'Enviando…' : 'Enviar a revisión'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
