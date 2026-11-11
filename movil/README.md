# movil/ — App Flutter (alertas_ciudadanas)

App para ciudadanos (reportar incidentes, ver alertas cercanas) y autoridades
(verificar reportes, despachar unidades y compartir su ubicación en vivo).

```powershell
copy .env.example .env     # ajusta API_BASE_URL (emulador: http://10.0.2.2:8000/api)
flutter pub get
flutter run
flutter analyze
flutter test
```

- API: `lib/core/network/dio_client.dart` (Dio + token JWT).
- Tiempo real: `lib/services/realtime_client.dart` (protocolo Pusher → Laravel Reverb).
- Notificaciones push: opcionales, requieren `android/app/google-services.json`.
- APKs generados: carpeta `releases/` (no se suben al repo).

Guía completa en el `README.md` de la raíz del repo.
