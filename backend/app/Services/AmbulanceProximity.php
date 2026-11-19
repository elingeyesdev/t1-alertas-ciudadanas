<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AmbulanceProximity
{
    public function progress(int $routeId, array $position): ?object
    {
        return DB::selectOne(<<<'SQL'
            WITH input AS (
                SELECT ST_Transform(geometry, 3857) AS line,
                       ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857) AS unit
                FROM dispatch_routes WHERE id = ? AND status = 'active'
            ), segments AS (
                SELECT part.path[1] AS index, part.geom AS segment, unit,
                       COALESCE(SUM(ST_Length(part.geom)) OVER (
                           ORDER BY part.path ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                       ), 0) AS offset_m,
                       ST_Length(line) AS length_m
                FROM input CROSS JOIN LATERAL ST_DumpSegments(line) AS part
                WHERE ST_Length(part.geom) > 0
            ), projected AS (
                SELECT index,
                       (offset_m + ST_Length(segment) * ST_LineLocatePoint(segment, unit)) AS along_m,
                       length_m,
                       ST_Distance(ST_Transform(ST_ClosestPoint(segment, unit), 4326)::geography,
                                   ST_Transform(unit, 4326)::geography) AS lateral_m
                FROM segments
            ), nearest AS (
                SELECT * FROM projected ORDER BY lateral_m, index LIMIT 1
            )
            SELECT nearest.along_m / nearest.length_m AS fraction, nearest.lateral_m,
                   EXISTS (SELECT 1 FROM projected AS other
                           WHERE other.lateral_m <= nearest.lateral_m + ?
                             AND ABS(other.along_m - nearest.along_m) > 75) AS ambiguous
            FROM nearest
            SQL, [$position['longitude'], $position['latitude'], $routeId, max(10, $position['accuracy_m'])]);
    }

    public function candidates(int $routeId, float $fraction, float $speedMps, array $position, ?string $userId = null): array
    {
        return DB::select(<<<'SQL'
            WITH route AS (
                SELECT ST_Transform(geometry, 3857) AS line,
                       ST_Transform(ST_LineSubstring(ST_Transform(geometry, 3857), ?, 1), 4326)::geography AS remaining,
                       ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS unit_position,
                       dispatches.attended_by
                FROM dispatch_routes JOIN dispatches ON dispatches.id = dispatch_routes.dispatch_id
                WHERE dispatch_routes.id = ? AND dispatch_routes.status = 'active' AND dispatches.state = 'en_curso'
            ), projected AS (
                SELECT locations.user_id, line,
                       ST_LineLocatePoint(line, ST_Transform(position::geometry, 3857)) AS fraction,
                       ST_Distance(position, remaining) AS lateral_m,
                       ST_DWithin(position, unit_position, ?) AS immediate
                FROM route CROSS JOIN ambulance_alert_locations AS locations
                JOIN users ON users.id = locations.user_id
                WHERE users.deleted_at IS NULL AND locations.user_id <> route.attended_by
                  AND (?::uuid IS NULL OR locations.user_id = ?::uuid)
                  AND captured_at >= NOW() - INTERVAL '60 seconds' AND accuracy_m <= 30
                  AND (ST_DWithin(position, remaining, ?) OR ST_DWithin(position, unit_position, ?))
            ), distances AS (
                SELECT user_id, lateral_m, immediate, ST_Length(ST_Transform(
                    ST_LineSubstring(line, ?, GREATEST(?, fraction)), 4326
                )::geography) AS ahead_m
                FROM projected WHERE immediate OR fraction > ?
            )
            SELECT user_id, lateral_m, ahead_m, CASE WHEN immediate THEN 0 ELSE ahead_m / ? END AS eta_s
            FROM distances WHERE immediate OR ahead_m / ? BETWEEN ? AND ?
            ORDER BY eta_s, user_id
            SQL, [
                $fraction, $position['longitude'], $position['latitude'], $routeId,
                config('alertas.ambulance.immediate_radius_m'), $userId, $userId,
                config('alertas.ambulance.corridor_m'), config('alertas.ambulance.immediate_radius_m'),
                $fraction, $fraction, $fraction, $speedMps, $speedMps,
                config('alertas.ambulance.min_eta_s'), config('alertas.ambulance.max_eta_s'),
            ]);
    }
}
