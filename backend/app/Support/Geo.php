<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Utilidades geográficas en PostgreSQL "puro" (sin PostGIS).
 *
 * Reemplaza a ST_DWithin / ST_MakePoint del backend NestJS usando la fórmula
 * de Haversine. Primero se filtra por un "bounding box" (usa el índice
 * lat/lng) y luego se calcula la distancia exacta en metros.
 */
class Geo
{
    public const EARTH_RADIUS_M = 6371000.0;

    /** Expresión SQL de distancia en metros entre (latCol,lngCol) y (?,?). */
    public static function distanceSql(string $latCol, string $lngCol): string
    {
        return '(2 * '.self::EARTH_RADIUS_M.' * asin(least(1, sqrt('
            ."power(sin(radians({$latCol} - ?) / 2), 2) + "
            ."cos(radians(?)) * cos(radians({$latCol})) * "
            ."power(sin(radians({$lngCol} - ?) / 2), 2)"
            .'))))';
    }

    /** Agrega a la consulta la condición "a menos de $meters metros". */
    public static function whereWithin(Builder $query, float $lat, float $lng, float $meters, string $latCol = 'latitude', string $lngCol = 'longitude'): Builder
    {
        [$minLat, $maxLat, $minLng, $maxLng] = self::boundingBox($lat, $lng, $meters);

        return $query
            ->whereBetween($latCol, [$minLat, $maxLat])
            ->whereBetween($lngCol, [$minLng, $maxLng])
            ->whereRaw(self::distanceSql($latCol, $lngCol).' <= ?', [$lat, $lat, $lng, $meters]);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} [minLat, maxLat, minLng, maxLng] */
    public static function boundingBox(float $lat, float $lng, float $meters): array
    {
        $dLat = rad2deg($meters / self::EARTH_RADIUS_M);
        $cos = max(cos(deg2rad($lat)), 0.000001);
        $dLng = rad2deg($meters / (self::EARTH_RADIUS_M * $cos));

        return [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];
    }

    /** Distancia Haversine en metros calculada en PHP. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1, sqrt($a)));
    }

    /**
     * Distancia mínima (metros) de un punto a una ruta (polilínea).
     * Proyección equirectangular local: suficientemente precisa para
     * distancias de cientos de metros dentro de una ciudad.
     *
     * @param  array<int, array{lat: float, lng: float}>  $route
     */
    public static function distanceToRoute(float $lat, float $lng, array $route): float
    {
        $cos = cos(deg2rad($lat));
        $toXY = fn (float $pLat, float $pLng) => [
            deg2rad($pLng - $lng) * self::EARTH_RADIUS_M * $cos,
            deg2rad($pLat - $lat) * self::EARTH_RADIUS_M,
        ];

        $min = INF;
        for ($i = 0; $i < count($route) - 1; $i++) {
            [$ax, $ay] = $toXY($route[$i]['lat'], $route[$i]['lng']);
            [$bx, $by] = $toXY($route[$i + 1]['lat'], $route[$i + 1]['lng']);
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $len2)) : 0;
            $px = $ax + $t * $dx;
            $py = $ay + $t * $dy;
            $min = min($min, sqrt($px * $px + $py * $py));
        }

        return $min;
    }
}
