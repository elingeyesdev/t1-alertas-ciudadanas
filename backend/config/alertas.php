<?php

/*
| Configuración propia del sistema de alertas (equivale a la carpeta config/
| del backend NestJS: jwt.config.ts, firebase.config.ts, etc.).
*/

return [
    'ambulance' => [
        'notifications_enabled' => (bool) env('AMBULANCE_NOTIFICATIONS_ENABLED', false),
        'cache_store' => env('AMBULANCE_CACHE_STORE', 'database'),
        'queue_connection' => env('AMBULANCE_QUEUE_CONNECTION', 'database'),
        'corridor_m' => (float) env('AMBULANCE_CORRIDOR_M', 100),
        'immediate_radius_m' => 25,
        'min_eta_s' => (float) env('AMBULANCE_MIN_ETA_S', 10),
        'max_eta_s' => (float) env('AMBULANCE_MAX_ETA_S', 90),
    ],

    // Zona horaria para mostrar fechas en el panel web (/alertas)
    'display_timezone' => env('DISPLAY_TIMEZONE', 'America/La_Paz'),

    'jwt' => [
        'secret' => env('JWT_SECRET'),
        // Acepta formatos como "1h", "30m", "72h", "7d" o segundos ("3600")
        'access_ttl' => env('ACCESS_TOKEN_VALIDITY_DURATION_IN_HOURS', '1h'),
        'refresh_ttl' => env('REFRESH_TOKEN_VALIDITY_DURATION_IN_HOURS', '72h'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'prueba'),
    ],

    'firebase' => [
        // Ruta al JSON de la cuenta de servicio de Firebase (FCM)
        // Si la ruta es relativa se toma desde la raíz del proyecto
        'credentials' => (function () {
            $path = env('FIREBASE_CREDENTIALS', 'storage/app/firebase-service-account.json');

            return preg_match('#^(/|[A-Za-z]:[\\\\/])#', $path) ? $path : base_path($path);
        })(),
    ],

    'reports' => [
        'expires_in_hours' => (int) env('REPORT_EXPIRES_IN_HOURS', 2),
        'nearby_max_age_hours' => 2,
        'similar_radius_m' => 100,
        'similar_max_age_minutes' => 200,
    ],

    'notifications' => [
        'nearby_radius_m' => (int) env('NOTIFY_NEARBY_RADIUS_M', 100),
        'route_corridor_radius_m' => (int) env('NOTIFY_ROUTE_RADIUS_M', 250),
        'route_cooldown_minutes' => 10,
    ],

    'trackings' => [
        // null = usa el CACHE_STORE por defecto. Pon "redis" si tienes Redis.
        'cache_store' => env('TRACKING_CACHE_STORE') ?: null,
        // Si un tracking no se actualiza en este tiempo se considera detenido
        'ttl_seconds' => (int) env('TRACKING_TTL_SECONDS', 120),
    ],

    // Panel de administración Blade/Tabler (/panel)
    'panel' => [
        // Token PÚBLICO de Mapbox (pk...). Vacío = mapa de OpenStreetMap.
        'mapbox_token' => env('MAPBOX_ACCESS_TOKEN', ''),
        // Centro inicial de los mapas: Santa Cruz de la Sierra
        'map_center' => [(float) env('MAP_CENTER_LAT', -17.7833), (float) env('MAP_CENTER_LNG', -63.1821)],
        // Tiempo real: mismo Reverb que usan la web y la app móvil
        'reverb' => [
            'key' => env('REVERB_APP_KEY', 'alertas-key'),
            'port' => (int) env('REVERB_PORT', 6001),
            'scheme' => env('REVERB_SCHEME', 'http'),
        ],
    ],
];
