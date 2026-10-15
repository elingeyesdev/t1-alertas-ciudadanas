import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:alertas_ciudadanas/main.dart';

void main() {
  testWidgets('requires an alert type and a description', (tester) async {
    await tester.pumpWidget(const MyApp());

    await tester.tap(find.text('Enviar alerta'));
    await tester.pumpAndSettle();

    expect(find.text('Seleccioná un tipo de alerta.'), findsOneWidget);
    expect(find.text('Contanos qué está pasando.'), findsOneWidget);
    expect(find.text('Tu alerta se registró localmente.'), findsNothing);
  });

  testWidgets('confirms local-only submission and resets the form', (tester) async {
    await tester.pumpWidget(const MyApp());

    await tester.tap(find.text('Seleccioná un tipo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bache en la calle').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('descriptionField')), 'Hay un bache grande.');
    await tester.tap(find.text('Enviar alerta'));
    await tester.pumpAndSettle();

    expect(find.text('Tu alerta se registró localmente.'), findsOneWidget);
    expect(find.text('No se envió a ningún servidor.'), findsOneWidget);
    expect(find.text('Hay un bache grande.'), findsNothing);
    expect(find.text('Seleccioná un tipo'), findsOneWidget);
  });
}
