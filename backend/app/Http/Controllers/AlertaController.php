<?php

namespace App\Http\Controllers;

use App\Enums\StateReport;
use App\Models\Report;
use App\Models\ReportType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel web de alertas recibidas (antes vivía en web/ con su propia tabla "alertas").
 * Ahora muestra los reportes reales que llegan desde la app y la web.
 */
class AlertaController extends Controller
{
    public const ESTADOS = [
        'activo' => 'Activo',
        'resuelto' => 'Resuelto',
        'vencido' => 'Vencido',
        'eliminado' => 'Eliminado',
    ];

    /**
     * Tabla con fecha, tipo y estado, filtrable por tipo y ordenada
     * de la más reciente a la más antigua.
     */
    public function index(Request $request): View
    {
        $tipos = ReportType::orderBy('name')->pluck('name', 'id')->all();

        $validated = $request->validate([
            'tipo' => ['nullable', 'integer', Rule::in(array_keys($tipos))],
        ]);

        $tipo = isset($validated['tipo']) ? (int) $validated['tipo'] : null;

        $alertas = Report::withTrashed()
            ->with('type')
            ->when($tipo, fn ($q) => $q->where('report_type_id', $tipo))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('alertas.index', [
            'alertas' => $alertas,
            'tipos' => $tipos,
            'estados' => self::ESTADOS,
            'tipoSeleccionado' => $tipo,
            'zonaHoraria' => config('alertas.display_timezone'),
        ]);
    }
}
