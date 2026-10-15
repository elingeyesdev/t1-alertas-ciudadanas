<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertaController extends Controller
{
    /**
     * Panel de alertas recibidas: tabla con fecha, tipo y estado,
     * filtrable por tipo y ordenada de la más reciente a la más antigua.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'tipo' => ['nullable', 'string', Rule::in(array_keys(Alerta::TIPOS))],
        ]);

        $tipo = $validated['tipo'] ?? null;

        $alertas = Alerta::query()
            ->deTipo($tipo)
            ->recientesPrimero()
            ->paginate(15)
            ->withQueryString();

        return view('alertas.index', [
            'alertas' => $alertas,
            'tipos' => Alerta::TIPOS,
            'tipoSeleccionado' => $tipo,
        ]);
    }
}
