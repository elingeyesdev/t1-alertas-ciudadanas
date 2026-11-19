import 'dart:async';

import 'package:alertas_ciudadanas/core/utils/error_handler.dart';
import 'package:alertas_ciudadanas/models/case_tracking_model.dart';
import 'package:alertas_ciudadanas/services/case_service.dart';
import 'package:alertas_ciudadanas/services/location_service.dart';
import 'package:alertas_ciudadanas/services/sos_service.dart';
import 'package:alertas_ciudadanas/views/case_tracking_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geocoding/geocoding.dart';
import 'package:url_launcher/url_launcher.dart';

/// HU-24 Botón de pánico.
/// 1) Cuenta regresiva de 5 s para cancelar, 2) envía el SOS con el GPS,
/// 3) muestra «unidad asignada» → «en camino» y los avisos al círculo.
class SosScreen extends StatefulWidget {
  const SosScreen({super.key});

  @override
  State<SosScreen> createState() => _SosScreenState();
}

enum _Phase { countdown, sending, active, error }

class _SosScreenState extends State<SosScreen> {
  static const _red = Color(0xFFD63939);
  static const _countdownSeconds = 5;

  static const _kinds = [
    ('general', 'Emergencia', Icons.sos_rounded),
    ('seguridad', 'Robo / agresión', Icons.local_police_rounded),
    ('medica', 'Médica', Icons.medical_services_rounded),
    ('incendio', 'Incendio', Icons.local_fire_department_rounded),
  ];

  final _sos = SosService();
  final _cases = CaseService();

  _Phase _phase = _Phase.countdown;
  int _secondsLeft = _countdownSeconds;
  String _kind = 'general';
  Timer? _countdown;
  Timer? _poll;
  SosResult? _result;
  CaseTrackingModel? _case;
  String? _error;
  final Set<String> _notified = {};

  @override
  void initState() {
    super.initState();
    _countdown = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      HapticFeedback.selectionClick();
      if (_secondsLeft <= 1) {
        timer.cancel();
        _send();
      } else {
        setState(() => _secondsLeft--);
      }
    });
  }

  @override
  void dispose() {
    _countdown?.cancel();
    _poll?.cancel();
    super.dispose();
  }

  void _cancel() {
    _countdown?.cancel();
    Navigator.of(context).pop();
  }

  Future<void> _send() async {
    setState(() => _phase = _Phase.sending);
    try {
      final position = await const LocationService().getCurrentLocation();
      String? zone;
      try {
        final places = await placemarkFromCoordinates(position.latitude, position.longitude)
            .timeout(const Duration(seconds: 3));
        if (places.isNotEmpty) {
          final p = places.first;
          zone = (p.subLocality?.isNotEmpty ?? false) ? p.subLocality : p.locality;
        }
      } catch (_) {
        // Sin nombre de zona también se envía: lo importante son las coordenadas.
      }

      final result = await _sos.trigger(
        latitude: position.latitude,
        longitude: position.longitude,
        kind: _kind,
        zone: zone,
      );
      if (!mounted) return;
      HapticFeedback.heavyImpact();
      setState(() {
        _result = result;
        _phase = _Phase.active;
      });
      _refreshCase();
      _poll = Timer.periodic(const Duration(seconds: 6), (_) => _refreshCase());
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e is LocationException ? e.message : parseError(e);
        _phase = _Phase.error;
      });
    }
  }

  Future<void> _refreshCase() async {
    final id = _result?.reportId;
    if (id == null || id == 0) return;
    try {
      final data = await _cases.fetchCase(id);
      if (mounted) setState(() => _case = data);
    } catch (_) {
      // Se reintenta en el siguiente ciclo.
    }
  }

  Future<void> _openWhatsApp(SosWhatsAppContact contact) async {
    try {
      final ok = await launchUrl(Uri.parse(contact.url), mode: LaunchMode.externalApplication);
      if (ok && mounted) setState(() => _notified.add(contact.url));
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('No se pudo abrir WhatsApp')));
    }
  }

  Future<void> _markSafe() async {
    final id = _result?.reportId;
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('¿Ya estás a salvo?'),
        content: const Text('Se cerrará la alerta SOS.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Sí, estoy a salvo')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await _sos.markSafe(id);
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).pop();
      messenger.showSnackBar(const SnackBar(content: Text('Alerta SOS cerrada. Nos alegra que estés bien.')));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(parseError(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      // Durante el envío no se puede salir por accidente.
      canPop: _phase != _Phase.sending,
      child: Scaffold(
        backgroundColor: _phase == _Phase.active ? null : _red,
        appBar: _phase == _Phase.active
            ? AppBar(title: const Text('SOS activo'), backgroundColor: _red, foregroundColor: Colors.white)
            : null,
        body: SafeArea(
          child: switch (_phase) {
            _Phase.countdown => _countdownView(),
            _Phase.sending => _sendingView(),
            _Phase.active => _activeView(),
            _Phase.error => _errorView(),
          },
        ),
      ),
    );
  }

  Widget _countdownView() {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Spacer(),
          const Text('Se enviará un SOS en', style: TextStyle(color: Colors.white, fontSize: 20)),
          const SizedBox(height: 12),
          Text('$_secondsLeft', style: const TextStyle(color: Colors.white, fontSize: 120, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          const Text('¿Qué tipo de emergencia es? (opcional)', style: TextStyle(color: Colors.white70)),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            children: [
              for (final kind in _kinds)
                ChoiceChip(
                  selected: _kind == kind.$1,
                  onSelected: (_) => setState(() => _kind = kind.$1),
                  avatar: Icon(kind.$3, size: 18, color: _kind == kind.$1 ? _red : Colors.white),
                  label: Text(kind.$2),
                  labelStyle: TextStyle(color: _kind == kind.$1 ? _red : Colors.white, fontWeight: FontWeight.bold),
                  selectedColor: Colors.white,
                  backgroundColor: Colors.white.withValues(alpha: 0.15),
                  showCheckmark: false,
                  side: const BorderSide(color: Colors.white54),
                ),
            ],
          ),
          const Spacer(),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: _red,
                padding: const EdgeInsets.symmetric(vertical: 18),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
              ),
              onPressed: _cancel,
              child: const Text('CANCELAR', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
            ),
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: () {
              _countdown?.cancel();
              _send();
            },
            child: const Text('Enviar ahora', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Widget _sendingView() {
    return const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircularProgressIndicator(color: Colors.white),
          SizedBox(height: 20),
          Text('Enviando tu ubicación…', style: TextStyle(color: Colors.white, fontSize: 18)),
        ],
      ),
    );
  }

  Widget _errorView() {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline_rounded, color: Colors.white, size: 64),
          const SizedBox(height: 16),
          Text(
            'No se pudo enviar el SOS.\n${_error ?? ''}',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontSize: 16),
          ),
          const SizedBox(height: 24),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: _red),
            onPressed: _send,
            child: const Text('Reintentar'),
          ),
          const SizedBox(height: 8),
          const Text('Si es urgente, llama al 110 (Policía) o al 118 (Ambulancia).', style: TextStyle(color: Colors.white70)),
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cerrar', style: TextStyle(color: Colors.white))),
        ],
      ),
    );
  }

  Widget _activeView() {
    final result = _result!;
    final stage = _case?.stage ?? (result.unitAssigned ? 'despachado' : 'creado');
    final dispatch = _case?.activeDispatch;

    final (IconData icon, String title, String subtitle) = switch (stage) {
      'en_camino' => (Icons.local_shipping_rounded, 'La unidad va en camino', dispatch?.stationName ?? ''),
      'despachado' => (Icons.assignment_ind_rounded, 'Unidad asignada', dispatch?.stationName ?? result.stationName ?? ''),
      'completado' || 'resuelto' => (Icons.check_circle_rounded, 'Atención completada', ''),
      _ => (Icons.hourglass_top_rounded, 'Buscando la unidad más cercana…', 'Tu alerta ya está en el panel de las autoridades.'),
    };

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(color: _red.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(18)),
          child: Row(
            children: [
              CircleAvatar(radius: 26, backgroundColor: _red, child: Icon(icon, color: Colors.white)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    if (subtitle.isNotEmpty) Text(subtitle),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Text(
          result.existing ? 'Ya tenías un SOS abierto: seguimos atendiendo ese mismo.' : 'Tu SOS #${result.reportId} fue enviado con tu ubicación.',
        ),
        const SizedBox(height: 20),
        const Text('Tu círculo de confianza', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 6),
        if (result.circlePushed > 0)
          Text('✅ ${result.circlePushed} contacto(s) con la app recibieron tu ubicación.'),
        if (result.whatsapp.isNotEmpty) ...[
          const SizedBox(height: 6),
          const Text('Avisa a los que no tienen la app (se abre WhatsApp con tu ubicación):'),
          for (final contact in result.whatsapp)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                _notified.contains(contact.url) ? Icons.check_circle_rounded : Icons.chat_rounded,
                color: _notified.contains(contact.url) ? Colors.green : const Color(0xFF25D366),
              ),
              title: Text(contact.name),
              trailing: FilledButton(
                style: FilledButton.styleFrom(backgroundColor: const Color(0xFF25D366)),
                onPressed: () => _openWhatsApp(contact),
                child: Text(_notified.contains(contact.url) ? 'Reenviar' : 'Avisar'),
              ),
            ),
        ],
        if (result.circlePushed == 0 && result.whatsapp.isEmpty)
          const Text('No tienes contactos en tu círculo. Agrégalos en Ajustes → Círculo de confianza.'),
        const SizedBox(height: 24),
        OutlinedButton.icon(
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => CaseTrackingScreen(reportId: result.reportId)),
          ),
          icon: const Icon(Icons.timeline_rounded),
          label: const Text('Ver seguimiento completo'),
        ),
        const SizedBox(height: 8),
        FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: const Color(0xFF6D8566), padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: _markSafe,
          icon: const Icon(Icons.verified_user_rounded),
          label: const Text('Ya estoy a salvo'),
        ),
      ],
    );
  }
}
