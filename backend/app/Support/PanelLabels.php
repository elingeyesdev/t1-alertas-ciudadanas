<?php

namespace App\Support;

/** Textos y colores (clases de Tabler) que usa el panel de administración. */
class PanelLabels
{
    public const REPORT_STATUS = [
        'activo' => ['Activa', 'green'],
        'resuelto' => ['Resuelta', 'azure'],
        'vencido' => ['Vencida', 'orange'],
        'eliminado' => ['Eliminada', 'red'],
    ];

    public const DISPATCH_STATE = [
        'en_curso' => ['En curso', 'yellow'],
        'completado' => ['Completado', 'green'],
        'cancelado' => ['Cancelado', 'secondary'],
    ];

    public const ROLES = [
        'usuario' => ['Ciudadano', 'purple'],
        'autoridad' => ['Autoridad', 'green'],
        'admin' => ['Administrador', 'blue'],
    ];

    public const PROFILE_TYPES = [
        'policia' => 'Policía',
        'bombero' => 'Bombero',
        'paramedico' => 'Paramédico',
    ];

    public const STATION_TYPES = [
        'policia' => ['Policía', 'blue', 'ti-shield'],
        'bombero' => ['Bomberos', 'red', 'ti-flame'],
        'hospital' => ['Hospital', 'green', 'ti-first-aid-kit'],
    ];

    /** HU-32: tipos de aviso preventivo => [texto, color, ícono]. */
    public const PUBLIC_ALERT_CATEGORIES = [
        'salud' => ['Salud', 'pink', 'ti-heartbeat'],
        'viaje' => ['Viaje', 'orange', 'ti-road'],
        'desastre' => ['Desastre natural', 'purple', 'ti-tornado'],
    ];

    public static function label(array $map, ?string $key): string
    {
        $value = $map[$key] ?? null;

        return is_array($value) ? $value[0] : ($value ?? (string) $key);
    }

    public static function color(array $map, ?string $key): string
    {
        $value = $map[$key] ?? null;

        return is_array($value) ? $value[1] : 'secondary';
    }

    /** Badge de Tabler listo para imprimir con {!! !!}. */
    public static function badge(array $map, ?string $key): string
    {
        return sprintf(
            '<span class="badge bg-%s-lt">%s</span>',
            e(self::color($map, $key)),
            e(self::label($map, $key))
        );
    }
}
