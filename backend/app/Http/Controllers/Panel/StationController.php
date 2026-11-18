<?php

namespace App\Http\Controllers\Panel;

use App\Enums\InstallationType;
use App\Models\EmergencyStation;
use App\Services\EmergencyStationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Módulo "Estaciones": policía, bomberos y hospitales. */
class StationController extends PanelController
{
    public function __construct(private readonly EmergencyStationService $stations) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::enum(InstallationType::class)],
        ]);

        $stations = EmergencyStation::withCount('dispatches')
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($filters['tipo'] ?? null, fn ($q, $t) => $q->where('installation_type', $t))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $counts = EmergencyStation::selectRaw('installation_type, count(*) as total')
            ->groupBy('installation_type')->pluck('total', 'installation_type');

        return view('panel.estaciones.index', [
            'stations' => $stations,
            'filters' => $filters,
            'stats' => [
                'total' => $counts->sum(),
                'policia' => $counts['policia'] ?? 0,
                'bombero' => $counts['bombero'] ?? 0,
                'hospital' => $counts['hospital'] ?? 0,
            ],
        ]);
    }

    public function create(): View
    {
        return view('panel.estaciones.form', ['station' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        return $this->run(fn () => $this->stations->create($data), 'Estación creada.', route('panel.estaciones.index'));
    }

    public function edit(int $id): View
    {
        return view('panel.estaciones.form', ['station' => EmergencyStation::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $station = EmergencyStation::findOrFail($id);
        $data = $request->validate($this->rules(), $this->messages());

        return $this->run(fn () => $station->update($data), 'Estación actualizada.', route('panel.estaciones.index'));
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->stations->remove($id), 'Estación eliminada.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'installation_type' => ['required', Rule::enum(InstallationType::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la estación.',
            'installation_type.required' => 'Elige el tipo de estación.',
            'latitude.required' => 'Marca la ubicación en el mapa.',
            'longitude.required' => 'Marca la ubicación en el mapa.',
        ];
    }
}
