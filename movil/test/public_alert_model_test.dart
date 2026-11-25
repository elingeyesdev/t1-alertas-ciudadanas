import 'package:flutter_test/flutter_test.dart';
import 'package:alertas_ciudadanas/models/public_alert_model.dart';
import 'package:alertas_ciudadanas/views/widgets/public_alerts_layer.dart';

void main() {
  group('HU-32 avisos preventivos', () {
    final json = {
      'id': 7,
      'category': 'desastre',
      'category_label': 'Desastre natural',
      'title': 'Alerta naranja por lluvias',
      'message': 'Evita cruzar canales.',
      'coordinates': [-63.1821, -17.7833],
      'radius_m': 2000,
      'expires_at': '2026-11-25T18:00:00-04:00',
    };

    test('lee el aviso que devuelve la API', () {
      final alert = PublicAlertModel.fromJson(json);

      expect(alert.id, 7);
      expect(alert.categoryLabel, 'Desastre natural');
      expect(alert.latitude, -17.7833);
      expect(alert.longitude, -63.1821);
      expect(alert.radiusMeters, 2000);
      expect(alert.expiresAt.toUtc(), DateTime.utc(2026, 11, 25, 22));
    });

    test('solo está vigente antes de su fecha de fin', () {
      final alert = PublicAlertModel.fromJson(json);

      expect(alert.isActiveAt(DateTime.utc(2026, 11, 25, 21, 59)), isTrue);
      expect(alert.isActiveAt(DateTime.utc(2026, 11, 25, 22, 1)), isFalse);
    });

    test('un aviso sin coordenadas no se dibuja', () {
      final alert = PublicAlertModel.fromJson({...json, 'coordinates': null});

      expect(alert.hasLocation, isFalse);
      expect(publicAlertCircles([alert]), isEmpty);
    });

    test('muestra el radio en metros o kilómetros', () {
      expect(formatPublicAlertRadius(500), '500 m');
      expect(formatPublicAlertRadius(2000), '2 km');
      expect(formatPublicAlertRadius(30000), '30 km');
    });
  });
}
