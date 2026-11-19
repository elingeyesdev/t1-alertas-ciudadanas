<?php

namespace App\Services;

use App\Enums\StateType;
use App\Models\Dispatch;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispatchRouteStore
{
    /**
     * Store a Directions route and return its version within the dispatch.
     */
    public function replace(int $dispatchId, array $route): int
    {
        $route = Validator::make($route, [
            'geometry' => ['required', 'array:type,coordinates'],
            'geometry.type' => ['required', Rule::in(['LineString'])],
            'geometry.coordinates' => ['required', 'array', 'list', 'min:2'],
            'geometry.coordinates.*' => ['required', 'array', 'list', 'size:2'],
            'geometry.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'geometry.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
            'distance' => ['required', 'numeric', 'min:0', 'max:40075017'],
            'duration' => ['required', 'numeric', 'min:0', 'max:2147483647'],
        ])->validate();

        $coordinates = array_map(
            static fn (array $point): array => [(float) $point[0], (float) $point[1]],
            $route['geometry']['coordinates'],
        );
        if (! array_any($coordinates, static fn (array $point): bool => $point !== $coordinates[0])) {
            throw ValidationException::withMessages(['geometry.coordinates' => 'La ruta necesita al menos dos puntos distintos.']);
        }

        $geometry = json_encode(['type' => 'LineString', 'coordinates' => $coordinates], JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($dispatchId, $route, $geometry): int {
            $dispatch = Dispatch::query()->lockForUpdate()->findOrFail($dispatchId);
            if ($dispatch->state !== StateType::EnCurso) {
                throw new DomainException('Solo se pueden guardar rutas para despachos en curso.');
            }

            $routes = DB::table('dispatch_routes')->where('dispatch_id', $dispatchId);
            $version = (int) (clone $routes)->max('route_version') + 1;
            $now = now();

            $routes->where('status', 'active')->update(['status' => 'replaced', 'updated_at' => $now]);

            DB::insert(
                'INSERT INTO dispatch_routes
                    (dispatch_id, route_version, status, geometry, distance_m, duration_s, created_at, updated_at)
                 VALUES (?, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), ?, ?, ?, ?)',
                [$dispatchId, $version, 'active', $geometry, $route['distance'], $route['duration'], $now, $now],
            );

            return $version;
        }, 3);
    }
}
