import 'package:alertas_ciudadanas/views/sos_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// HU-24: botón de pánico de la pantalla principal.
/// Se activa manteniéndolo presionado 3 segundos (evita toques accidentales).
class SosButton extends StatefulWidget {
  const SosButton({super.key});

  @override
  State<SosButton> createState() => _SosButtonState();
}

class _SosButtonState extends State<SosButton> with SingleTickerProviderStateMixin {
  static const _red = Color(0xFFD63939);
  late final AnimationController _hold;

  @override
  void initState() {
    super.initState();
    _hold = AnimationController(vsync: this, duration: const Duration(seconds: 3))
      ..addStatusListener((status) {
        if (status == AnimationStatus.completed) _activate();
      });
  }

  @override
  void dispose() {
    _hold.dispose();
    super.dispose();
  }

  void _start() {
    HapticFeedback.mediumImpact();
    _hold.forward(from: 0);
  }

  void _cancel() {
    if (_hold.isAnimating) _hold.reverse();
  }

  Future<void> _activate() async {
    HapticFeedback.heavyImpact();
    _hold.reset();
    await Navigator.of(context, rootNavigator: true).push(
      MaterialPageRoute(fullscreenDialog: true, builder: (_) => const SosScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
      decoration: BoxDecoration(
        color: _red.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _red.withValues(alpha: 0.35)),
      ),
      child: Column(
        children: [
          GestureDetector(
            onTapDown: (_) => _start(),
            onTapUp: (_) => _cancel(),
            onTapCancel: _cancel,
            child: AnimatedBuilder(
              animation: _hold,
              builder: (context, _) => SizedBox(
                width: 132,
                height: 132,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    SizedBox.expand(
                      child: CircularProgressIndicator(
                        value: _hold.value,
                        strokeWidth: 8,
                        color: _red,
                        backgroundColor: _red.withValues(alpha: 0.15),
                      ),
                    ),
                    Container(
                      width: 108 + 8 * _hold.value,
                      height: 108 + 8 * _hold.value,
                      decoration: BoxDecoration(
                        color: _red,
                        shape: BoxShape.circle,
                        boxShadow: [BoxShadow(color: _red.withValues(alpha: 0.4), blurRadius: 16)],
                      ),
                      alignment: Alignment.center,
                      child: const Text(
                        'SOS',
                        style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: 2),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          AnimatedBuilder(
            animation: _hold,
            builder: (context, _) => Text(
              _hold.value > 0 ? 'Sigue presionando…' : 'Mantén presionado 3 segundos para pedir ayuda',
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Se envía tu ubicación, se asigna la unidad más cercana y se avisa a tu círculo de confianza.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7)),
          ),
        ],
      ),
    );
  }
}
