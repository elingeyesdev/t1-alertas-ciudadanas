<?php

namespace App\Http\Controllers;

use App\Services\SosService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** HU-24 Botón de pánico (SOS). */
class SosController extends Controller
{
    public function __construct(private readonly SosService $sos) {}

    /** POST /api/sos — la ubicación GPS crea una alerta prioritaria y asigna una unidad al instante. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'kind' => ['nullable', Rule::in(SosService::kinds())],
            'zone' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'latitude.required' => 'No se pudo obtener tu ubicación. Activa el GPS.',
            'longitude.required' => 'No se pudo obtener tu ubicación. Activa el GPS.',
        ]);

        $result = $this->sos->trigger($request->user(), $data);

        return response()->json($result, $result['existing'] ? 200 : 201);
    }
}
