import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

typedef RealtimeHandler = void Function(Map<String, dynamic> data);

/// Cliente mínimo del protocolo Pusher para conectarse a Laravel Reverb.
///
/// Reemplaza a `socket_io_client`. Canales públicos que usa la app:
///  - "reports"   → evento "newReport"
///  - "trackings" → eventos "tracking_started" y "tracking_stopped"
///
/// Configuración (archivo .env de la app):
///  - REVERB_HOST      (por defecto, el mismo host de API_BASE_URL)
///  - REVERB_PORT      (por defecto 6001)
///  - REVERB_APP_KEY   (por defecto "alertas-key", igual que el backend)
///  - REVERB_SCHEME    (http o https)
class RealtimeClient {
  RealtimeClient._internal();
  static final RealtimeClient _instance = RealtimeClient._internal();
  factory RealtimeClient() => _instance;

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _subscription;
  Timer? _pingTimer;
  Timer? _reconnectTimer;
  bool _wantConnected = false;
  bool _connected = false;
  int _retries = 0;

  /// canal → evento → funciones que lo escuchan
  final Map<String, Map<String, List<RealtimeHandler>>> _handlers = {};
  final StreamController<bool> _connectionController =
      StreamController<bool>.broadcast();

  bool get isConnected => _connected;

  /// Emite true al conectarse y false al desconectarse.
  Stream<bool> get connectionChanges => _connectionController.stream;

  /// Registra una función para un evento de un canal y se asegura de estar conectado.
  void listen(String channel, String event, RealtimeHandler handler) {
    final isNewChannel = !_handlers.containsKey(channel);
    _handlers.putIfAbsent(channel, () => {});
    _handlers[channel]!.putIfAbsent(event, () => []).add(handler);

    if (isNewChannel && _connected) {
      _subscribe(channel);
    }
    connect();
  }

  /// Deja de escuchar un canal. Si ya no queda ninguno, cierra la conexión.
  void stopListening(String channel) {
    if (_handlers.remove(channel) != null && _connected) {
      _send({
        'event': 'pusher:unsubscribe',
        'data': {'channel': channel},
      });
    }
    if (_handlers.isEmpty) {
      disconnect();
    }
  }

  void connect() {
    _wantConnected = true;
    if (_channel == null) {
      _open();
    }
  }

  void disconnect() {
    _wantConnected = false;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    _closeSocket();
  }

  Uri _buildUri() {
    Map<String, String> env = const {};
    try {
      env = dotenv.env;
    } catch (_) {
      // .env no cargado: se usan valores por defecto
    }

    final apiUrl = Uri.tryParse(env['API_BASE_URL'] ?? '');
    final scheme = (env['REVERB_SCHEME'] ??
            (apiUrl != null && apiUrl.scheme == 'https' ? 'https' : 'http'))
        .trim();
    final configuredHost = (env['REVERB_HOST'] ?? '').trim();
    final host = configuredHost.isNotEmpty
        ? configuredHost
        : (apiUrl != null && apiUrl.host.isNotEmpty ? apiUrl.host : 'localhost');
    final port = int.tryParse((env['REVERB_PORT'] ?? '').trim()) ??
        (scheme == 'https' ? 443 : 6001);
    final key = (env['REVERB_APP_KEY'] ?? 'alertas-key').trim();

    return Uri(
      scheme: scheme == 'https' ? 'wss' : 'ws',
      host: host,
      port: port,
      path: '/app/$key',
      queryParameters: const {
        'protocol': '7',
        'client': 'dart',
        'version': '1.0',
        'flash': 'false',
      },
    );
  }

  void _open() {
    final uri = _buildUri();
    debugPrint('Conectando a Laravel Reverb: $uri');
    try {
      final channel = WebSocketChannel.connect(uri);
      _channel = channel;
      _subscription = channel.stream.listen(
        _onMessage,
        onDone: _onClosed,
        onError: (Object error) {
          debugPrint('Error en WebSocket de Reverb: $error');
          _onClosed();
        },
        cancelOnError: true,
      );
    } catch (e) {
      debugPrint('No se pudo abrir el WebSocket de Reverb: $e');
      _onClosed();
    }
  }

  void _onMessage(dynamic raw) {
    Map<String, dynamic> message;
    try {
      message = Map<String, dynamic>.from(jsonDecode(raw as String) as Map);
    } catch (_) {
      return;
    }

    final event = message['event'] as String? ?? '';

    switch (event) {
      case 'pusher:connection_established':
        _connected = true;
        _retries = 0;
        _connectionController.add(true);
        debugPrint('Conectado a Laravel Reverb');
        for (final channel in _handlers.keys) {
          _subscribe(channel);
        }
        _startPing(_decodeData(message['data'])['activity_timeout']);
        return;
      case 'pusher:ping':
        _send({'event': 'pusher:pong', 'data': {}});
        return;
      case 'pusher:pong':
      case 'pusher_internal:subscription_succeeded':
        return;
      case 'pusher:error':
        debugPrint('Reverb respondió un error: ${message['data']}');
        return;
    }

    final channel = message['channel'] as String?;
    if (channel == null) return;
    final handlers = _handlers[channel]?[event];
    if (handlers == null || handlers.isEmpty) return;

    final data = _decodeData(message['data']);
    for (final handler in List<RealtimeHandler>.from(handlers)) {
      try {
        handler(data);
      } catch (e) {
        debugPrint('Error procesando el evento $event de $channel: $e');
      }
    }
  }

  /// Reverb envía "data" como texto JSON; se convierte a Map.
  Map<String, dynamic> _decodeData(dynamic data) {
    try {
      if (data is String) {
        final decoded = jsonDecode(data);
        if (decoded is Map) return Map<String, dynamic>.from(decoded);
      } else if (data is Map) {
        return Map<String, dynamic>.from(data);
      }
    } catch (_) {}
    return <String, dynamic>{};
  }

  void _subscribe(String channel) {
    _send({
      'event': 'pusher:subscribe',
      'data': {'channel': channel},
    });
  }

  void _send(Map<String, dynamic> message) {
    try {
      _channel?.sink.add(jsonEncode(message));
    } catch (e) {
      debugPrint('No se pudo enviar a Reverb: $e');
    }
  }

  void _startPing(dynamic activityTimeout) {
    _pingTimer?.cancel();
    final seconds = activityTimeout is int && activityTimeout > 0 ? activityTimeout : 30;
    _pingTimer = Timer.periodic(Duration(seconds: seconds), (_) {
      _send({'event': 'pusher:ping', 'data': {}});
    });
  }

  void _onClosed() {
    final wasConnected = _connected;
    _closeSocket();
    if (wasConnected) {
      debugPrint('Desconectado de Laravel Reverb');
    }

    if (_wantConnected) {
      // Reintento con espera creciente: 1, 2, 4, 8, 16 y máximo 30 segundos
      final delay = min(30, pow(2, _retries).toInt());
      _retries++;
      _reconnectTimer?.cancel();
      _reconnectTimer = Timer(Duration(seconds: delay), () {
        if (_wantConnected && _channel == null) _open();
      });
    }
  }

  void _closeSocket() {
    _pingTimer?.cancel();
    _pingTimer = null;
    _subscription?.cancel();
    _subscription = null;
    try {
      _channel?.sink.close();
    } catch (_) {}
    _channel = null;
    if (_connected) {
      _connected = false;
      _connectionController.add(false);
    }
  }
}
