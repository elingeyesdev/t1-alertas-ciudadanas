<?php

namespace App\Http\Controllers\Panel;

use App\Enums\PublicAlertCategory;
use App\Models\PublicAlert;
use App\Services\PublicAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Módulo "Avisos preventivos" (HU-32): salud, viaje o desastres naturales por zona. */
class PublicAlertController extends PanelController
{
    /** Radios que puede elegir el administrador (metros => texto). */
    public const RADII = [500 => '500 m', 1000 => '1 km', 2000 => '2 km', 5000 => '5 km', 10000 => '10 km', 30000 => 'Toda la ciudad (30 km)'];

    /** Vigencia en horas => texto. */
    public const DURATIONS = [1 => '1 hora', 3 => '3 horas', 6 => '6 horas', 12 => '12 horas', 24 => '1 día', 48 => '2 días', 72 => '3 días', 168 => '1 semana'];

    public function __construct(private readonly PublicAlerts $alerts) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'estado' => ['nullable', Rule::in(['vigentes', 'finalizados'])],
            'categoria' => ['nullable', Rule::enum(PublicAlertCategory::class)],
        ]);

        $alerts = PublicAlert::with('creator')
            ->when(($filters['estado'] ?? null) === 'vigentes', fn ($q) => $q->active())
            ->when(($filters['estado'] ?? null) === 'finalizados', fn ($q) => $q->where('expires_at', '<=', now()))
            ->when($filters['categoria'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('panel.avisos.index', [
            'alerts' => $alerts,
            'filters' => $filters,
            'stats' => [
                'vigentes' => PublicAlert::active()->count(),
                'total' => PublicAlert::count(),
                'notificados' => (int) PublicAlert::sum('notified_count'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('panel.avisos.form', ['radii' => self::RADII, 'durations' => self::DURATIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::enum(PublicAlertCategory::class)],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', Rule::in(array_keys(self::RADII))],
            'hours' => ['required', 'integer', Rule::in(array_keys(self::DURATIONS))],
        ], [
            'category.required' => 'Elige el tipo de aviso.',
            'title.required' => 'Escribe el título del aviso.',
            'message.required' => 'Escribe el mensaje para la población.',
            'latitude.required' => 'Marca el centro de la zona en el mapa.',
            'longitude.required' => 'Marca el centro de la zona en el mapa.',
            'radius_m.required' => 'Elige el tamaño de la zona.',
            'hours.required' => 'Elige hasta cuándo está vigente.',
        ]);

        $admin = auth('web')->user();
        $alert = $this->alerts->publish($data, $admin);
        $people = $alert->notified_count === 1 ? '1 ciudadano' : "{$alert->notified_count} ciudadanos";

        return redirect()->route('panel.avisos.index')
            ->with('success', "Aviso publicado. Se notificó a {$people} dentro de la zona.");
    }

    public function finish(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->alerts->finish($id), 'Aviso finalizado: ya no se muestra en los mapas.');
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->alerts->remove($id), 'Aviso eliminado.');
    }
}
