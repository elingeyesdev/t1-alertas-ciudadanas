import 'package:alertas_ciudadanas/core/utils/error_handler.dart';
import 'package:alertas_ciudadanas/models/trust_circle_model.dart';
import 'package:alertas_ciudadanas/services/trust_circle_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

/// HU-25 Círculo de confianza (estilo Life360).
/// Hasta 5 contactos que reciben tu ubicación solo cuando activas el SOS.
class TrustCircleScreen extends StatefulWidget {
  const TrustCircleScreen({super.key});

  @override
  State<TrustCircleScreen> createState() => _TrustCircleScreenState();
}

class _TrustCircleScreenState extends State<TrustCircleScreen> {
  static const _accent = Color(0xFFAF6D58);

  final _service = TrustCircleService();
  TrustCircleModel? _circle;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final circle = await _service.fetch();
      if (!mounted) return;
      setState(() {
        _circle = circle;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = parseError(e);
        _loading = false;
      });
    }
  }

  void _toast(String message, {bool ok = true}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: ok ? const Color(0xFF6D8566) : const Color(0xFFB64D4C),
    ));
  }

  Future<void> _run(Future<void> Function() action, String success) async {
    try {
      await action();
      _toast(success);
      await _load();
    } catch (e) {
      _toast(parseError(e), ok: false);
    }
  }

  // ─── Agregar / editar contacto sin la app ─────────────────────────────────
  Future<void> _editContact([TrustContactModel? contact]) async {
    final name = TextEditingController(text: contact?.name ?? '');
    final phone = TextEditingController(text: contact?.phone ?? '');
    final canEditPhone = contact == null || !contact.hasApp;

    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(contact == null ? 'Agregar contacto' : 'Editar contacto'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Nombre'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: phone,
              enabled: canEditPhone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Teléfono (WhatsApp)',
                helperText: canEditPhone ? 'Ej.: 70012345' : 'Es el número de su cuenta en la app',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Guardar')),
        ],
      ),
    );
    if (saved != true) return;

    if (contact == null) {
      await _run(() => _service.addContact(name.text.trim(), phone.text.trim()), 'Contacto agregado');
    } else {
      await _run(
        () => _service.updateContact(contact.id, name: name.text.trim(), phone: canEditPhone ? phone.text.trim() : null),
        'Contacto actualizado',
      );
    }
  }

  Future<void> _removeContact(TrustContactModel contact) async {
    final ok = await _confirm('Quitar a ${contact.name}', 'Ya no recibirá tu ubicación si activas el SOS.');
    if (ok) await _run(() => _service.removeContact(contact.id), 'Contacto quitado');
  }

  Future<void> _leave(TrustMembershipModel membership) async {
    final ok = await _confirm('Salir del círculo', 'Dejarás de recibir los avisos de ${membership.ownerName}.');
    if (ok) await _run(() => _service.leave(membership.id), 'Saliste del círculo');
  }

  Future<bool> _confirm(String title, String message) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Aceptar')),
        ],
      ),
    );
    return result == true;
  }

  // ─── Invitar con código ───────────────────────────────────────────────────
  Future<void> _invite() async {
    try {
      final invite = await _service.createInvite();
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Código de invitación'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Pídele a tu contacto que abra la app → Círculo de confianza → «Tengo un código».'),
              const SizedBox(height: 16),
              SelectableText(
                invite.code,
                style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, letterSpacing: 6, color: _accent),
              ),
              const SizedBox(height: 8),
              const Text('Vence en 48 horas y sirve una sola vez.', style: TextStyle(fontSize: 12)),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () {
                Clipboard.setData(ClipboardData(text: invite.code));
                Navigator.pop(ctx);
                _toast('Código copiado');
              },
              child: const Text('Copiar'),
            ),
            FilledButton.icon(
              onPressed: () {
                Navigator.pop(ctx);
                _openWhatsApp(invite.shareText);
              },
              icon: const Icon(Icons.share_rounded, size: 18),
              label: const Text('Enviar por WhatsApp'),
            ),
          ],
        ),
      );
    } catch (e) {
      _toast(parseError(e), ok: false);
    }
  }

  Future<void> _openWhatsApp(String text) async {
    final uri = Uri.parse('https://wa.me/?text=${Uri.encodeComponent(text)}');
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened) _toast('No se pudo abrir WhatsApp', ok: false);
    } catch (_) {
      _toast('No se pudo abrir WhatsApp', ok: false);
    }
  }

  Future<void> _join() async {
    final code = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Tengo un código'),
        content: TextField(
          controller: code,
          textCapitalization: TextCapitalization.characters,
          maxLength: 6,
          decoration: const InputDecoration(labelText: 'Código de invitación'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Unirme')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      final owner = await _service.join(code.text.trim());
      _toast('Ahora estás en el círculo de $owner');
      await _load();
    } catch (e) {
      _toast(parseError(e), ok: false);
    }
  }

  // ─── Pantalla ─────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Círculo de confianza')),
      body: RefreshIndicator(onRefresh: _load, child: _body(context)),
    );
  }

  Widget _body(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    final circle = _circle;
    if (circle == null) {
      return ListView(padding: const EdgeInsets.all(24), children: [
        const SizedBox(height: 80),
        Text(_error ?? 'No se pudo cargar tu círculo.', textAlign: TextAlign.center),
        Center(child: TextButton(onPressed: _load, child: const Text('Reintentar'))),
      ]);
    }

    final muted = Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: _accent.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(14)),
          child: const Row(
            children: [
              Icon(Icons.shield_rounded, color: _accent),
              SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Tu ubicación solo se envía a tu círculo cuando activas el botón de pánico (SOS). '
                  'Nunca se comparte de forma constante.',
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            const Expanded(child: Text('Mis contactos', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold))),
            Text('${circle.contacts.length}/${circle.maxContacts}', style: TextStyle(color: muted)),
          ],
        ),
        const SizedBox(height: 8),
        if (circle.contacts.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Text('Todavía no agregaste a nadie.', style: TextStyle(color: muted)),
          ),
        for (final contact in circle.contacts)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: _accent,
                child: Text(
                  contact.name.isEmpty ? '?' : contact.name[0].toUpperCase(),
                  style: const TextStyle(color: Colors.white),
                ),
              ),
              title: Text(contact.name),
              subtitle: Text('${contact.phone} · ${contact.hasApp ? 'Con la app (push)' : 'Sin la app (WhatsApp)'}'),
              trailing: PopupMenuButton<String>(
                onSelected: (value) => value == 'edit' ? _editContact(contact) : _removeContact(contact),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Editar')),
                  PopupMenuItem(value: 'remove', child: Text('Quitar')),
                ],
              ),
            ),
          ),
        const SizedBox(height: 8),
        if (circle.isFull)
          Text('Tu círculo está completo. Quita a alguien para agregar otro contacto.', style: TextStyle(color: muted))
        else ...[
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: _accent, padding: const EdgeInsets.symmetric(vertical: 14)),
            onPressed: _invite,
            icon: const Icon(Icons.qr_code_rounded),
            label: const Text('Invitar con código (tiene la app)'),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
            onPressed: () => _editContact(),
            icon: const Icon(Icons.person_add_alt_1_rounded),
            label: const Text('Agregar con nombre y teléfono'),
          ),
        ],
        const SizedBox(height: 28),
        const Text('Círculos a los que perteneces', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        if (circle.memberships.isEmpty)
          Text('Nadie te agregó todavía.', style: TextStyle(color: muted)),
        for (final membership in circle.memberships)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.group_rounded),
            title: Text(membership.ownerName),
            subtitle: const Text('Recibirás su ubicación si activa el SOS'),
            trailing: TextButton(onPressed: () => _leave(membership), child: const Text('Salir')),
          ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: _join,
          icon: const Icon(Icons.vpn_key_rounded),
          label: const Text('Tengo un código'),
        ),
      ],
    );
  }
}
