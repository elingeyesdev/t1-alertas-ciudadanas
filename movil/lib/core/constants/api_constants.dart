import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

class ApiConstants {
  static String get apiBaseUrl =>
      _read('API_BASE_URL') ?? (kDebugMode ? 'http://127.0.0.1:8080/api' : '');

  static String get mapboxToken => _read('MAPBOX_ACCESS_TOKEN') ?? '';

  static String? _read(String key) {
    if (!dotenv.isInitialized) return null;
    final value = dotenv.env[key]?.trim();
    return value == null || value.isEmpty ? null : value;
  }
}
