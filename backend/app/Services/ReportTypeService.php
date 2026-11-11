<?php

namespace App\Services;

use App\Models\ReportType;

class ReportTypeService
{
    public function create(string $name, int $baseWeight = 1): ReportType
    {
        return ReportType::create(['name' => $name, 'base_weight' => $baseWeight]);
    }

    public function findAll()
    {
        return ReportType::orderBy('id')->get();
    }

    public function remove(int $id): array
    {
        $item = ReportType::find($id);
        if ($item && $item->reports()->withoutGlobalScopes()->exists()) {
            abort(409, 'El tipo tiene reportes asociados y no se puede eliminar');
        }

        if (ReportType::whereKey($id)->delete() === 0) {
            abort(404, "El tipo con ID {$id} no se encontro");
        }

        return ['message' => 'Tipo eliminado correctamente'];
    }
}
