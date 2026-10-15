import 'package:flutter/material.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(



      seedColor: const Color(0xFF176B5B),
      surface: const Color(0xFFF7F8F5),



      
    );

    return MaterialApp(
      title: 'Alertas Ciudadanas',
      theme: ThemeData(
        colorScheme: colorScheme,
        useMaterial3: true,
        scaffoldBackgroundColor: colorScheme.surface,
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: colorScheme.outlineVariant),
          ),
          contentPadding: const EdgeInsets.all(16),
        ),
      ),
      home: const AlertSubmissionPage(),
    );
  }
}

class AlertSubmissionPage extends StatefulWidget {
  const AlertSubmissionPage({super.key});

  @override
  State<AlertSubmissionPage> createState() => _AlertSubmissionPageState();
}

class _AlertSubmissionPageState extends State<AlertSubmissionPage> {
  static const _alertTypes = [
    'Bache en la calle',
    'Alumbrado público',
    'Basura en la vía pública',
    'Otro',
  ];

  final _formKey = GlobalKey<FormState>();
  final _descriptionController = TextEditingController();
  String? _selectedType;
  bool _submittedLocally = false;
  int _formVersion = 0;

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  void _submit() {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) {
      setState(() => _submittedLocally = false);
      return;
    }

    _formKey.currentState!.reset();
    _descriptionController.clear();
    setState(() {
      _selectedType = null;
      _submittedLocally = true;
      _formVersion++;
    });
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Alertas Ciudadanas'),
        backgroundColor: colors.surface,
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 640),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: colors.primaryContainer,
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Icon(Icons.campaign_outlined, color: colors.onPrimaryContainer, size: 30),
                ),
                const SizedBox(height: 20),
                Text('Contanos qué pasa en tu barrio',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Text(
                  'Describí una situación que necesite atención de la comunidad.',
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: colors.onSurfaceVariant),
                ),
                const SizedBox(height: 24),
                if (_submittedLocally) ...[
                  Semantics(
                    liveRegion: true,
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: colors.tertiaryContainer,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.check_circle_outline, color: colors.onTertiaryContainer),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  'Tu alerta se registró localmente.',
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                        color: colors.onTertiaryContainer,
                                        fontWeight: FontWeight.w700,
                                      ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('No se envió a ningún servidor.', style: TextStyle(color: colors.onTertiaryContainer)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                ],
                Card(
                  margin: EdgeInsets.zero,
                  color: colors.surfaceContainerLowest,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                    side: BorderSide(color: colors.outlineVariant),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text('Nueva alerta', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 20),
                          DropdownButtonFormField<String>(
                            key: ValueKey(_formVersion),
                            initialValue: _selectedType,
                            decoration: const InputDecoration(labelText: 'Tipo de alerta'),
                            hint: const Text('Seleccioná un tipo'),
                            items: _alertTypes
                                .map((type) => DropdownMenuItem(value: type, child: Text(type)))
                                .toList(),
                            onChanged: (value) => setState(() {
                              _selectedType = value;
                              _submittedLocally = false;
                            }),
                            validator: (value) => value == null ? 'Seleccioná un tipo de alerta.' : null,
                          ),
                          const SizedBox(height: 18),
                          TextFormField(
                            key: const Key('descriptionField'),
                            controller: _descriptionController,
                            onChanged: (_) {
                              if (_submittedLocally) {
                                setState(() => _submittedLocally = false);
                              }
                            },
                            minLines: 4,
                            maxLines: 6,
                            textCapitalization: TextCapitalization.sentences,
                            decoration: const InputDecoration(
                              labelText: 'Descripción',
                              hintText: 'Agregá detalles para entender mejor la situación',
                              alignLabelWithHint: true,
                            ),
                            validator: (value) => value == null || value.trim().isEmpty
                                ? 'Contanos qué está pasando.'
                                : null,
                          ),
                          const SizedBox(height: 24),
                          SizedBox(
                            height: 52,
                            child: FilledButton.icon(
                              onPressed: _submit,
                              icon: const Icon(Icons.send_outlined),
                              label: const Text('Enviar alerta'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Por ahora, esta acción solo muestra una confirmación en este dispositivo.',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
