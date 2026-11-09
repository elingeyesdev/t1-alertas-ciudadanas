import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:alertas_ciudadanas/app/app.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  FlutterForegroundTask.initCommunicationPort();
  // Firebase es opcional: sin google-services.json la app funciona igual,
  // solo que sin notificaciones push.
  try {
    await Firebase.initializeApp();
  } catch (e) {
    debugPrint('Firebase no configurado (sin notificaciones push): $e');
  }
  try {
    await dotenv.load(fileName: '.env');
  } catch (_) {
    // .env opcional: Mapbox y API usan fallbacks en código.
  }
  runApp(const App());
}



