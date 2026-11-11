<?php

namespace App\Services;

use App\Http\Resources\EmergencyStationResource;
use App\Models\EmergencyStation;

class EmergencyStationService
{
    public function findAll()
    {
        return EmergencyStationResource::collection(EmergencyStation::orderBy('id')->get());
    }

    public function create(array $data): EmergencyStationResource
    {
        return new EmergencyStationResource(EmergencyStation::create($data));
    }

    public function update(int $id, string $name): EmergencyStationResource
    {
        $station = EmergencyStation::find($id);
        if (! $station) {
            abort(404, "La estación de emergencia con ID {$id} no se encontró.");
        }
        $station->update(['name' => $name]);

        return new EmergencyStationResource($station);
    }

    public function remove(int $id): array
    {
        $station = EmergencyStation::find($id);
        if (! $station) {
            abort(404, "La estación de emergencia con ID {$id} no se encontró.");
        }
        $station->delete();

        return ['message' => 'Estación de emergencia eliminada correctamente.'];
    }
}
