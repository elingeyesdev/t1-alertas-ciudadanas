<?php

namespace App\Http\Controllers\Panel;

use App\Jobs\NotifyAmbulanceAssignment;

use App\Enums\StateReport;
use App\Enums\StateType;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\User;
use App\Services\DispatchService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Módulo "Alertas" del panel: listado, detalle y acciones sobre los reportes. */
class ReportController extends PanelController
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly DispatchService $dispatches,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::enum(StateReport::class)],
            'verificacion' => ['nullable', Rule::in(['si', 'no'])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $reports = Report::withTrashed()
            ->with('type')
            ->withCount(['images', 'dispatches'])
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('description', 'ilike', "%{$s}%")
                ->orWhere('zone', 'ilike', "%{$s}%")
                ->orWhere('id', ctype_digit($s) ? (int) $s : 0)))
            ->when($filters['tipo'] ?? null, fn ($q, $t) => $q->where('report_type_id', $t))
            ->when($filters['estado'] ?? null, fn ($q, $e) => $q->where('status', $e))
            ->when(($filters['verificacion'] ?? null) === 'si', fn ($q) => $q->where('verified', true))
            ->when(($filters['verificacion'] ?? null) === 'no', fn ($q) => $q->where('verified', false))
            ->when($filters['desde'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['hasta'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            // HU-24: los SOS activos van primero
            ->orderByRaw("(is_sos AND status = 'activo') DESC")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = Report::withTrashed()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('panel.reportes.index', [
            'reports' => $reports,
            'types' => ReportType::orderBy('name')->get(),
            'filters' => $filters,
            'stats' => [
                'total' => $counts->sum(),
                'activas' => $counts['activo'] ?? 0,
                'resueltas' => $counts['resuelto'] ?? 0,
                'cerradas' => ($counts['vencido'] ?? 0) + ($counts['eliminado'] ?? 0),
            ],
        ]);
    }

    public function show(int $id): View
    {
        $report = Report::withTrashed()
            ->with([
                'type', 'creator', 'images',
                'dispatches' => fn ($q) => $q->latest('recorded_at'),
                'dispatches.destination', 'dispatches.attendedBy.authorityProfile',
                'comments' => fn ($q) => $q->whereNull('parent_comment_id')->latest(),
                'comments.creator', 'comments.responses.creator',
            ])
            ->findOrFail($id);

        return view('panel.reportes.show', [
            'report' => $report,
            'stations' => EmergencyStation::orderBy('name')->get(),
            'authorities' => User::with('authorityProfile')
                ->whereHas('role', fn ($q) => $q->where('name', 'autoridad'))
                ->orderBy('first_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('panel.reportes.create', ['types' => ReportType::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'integer', 'exists:report_types,id'],
            'description' => ['required', 'string', 'max:250'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'zone' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:10240'],
        ], [
            'latitude.required' => 'Marca la ubicación en el mapa.',
            'longitude.required' => 'Marca la ubicación en el mapa.',
        ]);

        $data['userId'] = Auth::guard('web')->id();
        $created = null;

        $response = $this->run(function () use ($data, $request, &$created) {
            $created = $this->reports->create($data, $request->file('image'));
        }, 'Alerta creada y publicada en el mapa.');

        return $created ? redirect()->route('panel.alertas.show', $created->resource->id)->with('success', 'Alerta creada y publicada en el mapa.') : $response;
    }

    public function verify(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->reports->verifyReport($id), 'Alerta verificada. Se avisó a quien la reportó.');
    }

    public function resolve(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->reports->resolveReport($id), 'Alerta marcada como resuelta.');
    }

    public function reactivate(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->reports->reactivateReport($id), 'Alerta reactivada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        return $this->run(fn () => $this->reports->remove($id), 'Alerta eliminada.');
    }

    public function storeDispatch(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer', 'exists:emergency_stations,id'],
            'authority_id' => ['required', 'uuid', 'exists:users,id'],
        ], [
            'station_id.required' => 'Elige la estación que atiende.',
            'authority_id.required' => 'Elige la autoridad asignada.',
        ]);

        return $this->run(
            function () use ($id, $data) {
                $dispatch = $this->dispatches->create($id, (int) $data['station_id'], $data['authority_id']);
                NotifyAmbulanceAssignment::dispatch($dispatch->id)
                    ->onConnection(config('alertas.ambulance.queue_connection'))->onQueue('ambulance')->afterCommit();
            },
            'Unidad despachada.'
        );
    }

    public function updateDispatch(Request $request, int $dispatchId): RedirectResponse
    {
        $data = $request->validate(['state' => ['required', Rule::enum(StateType::class)]]);

        return $this->run(fn () => $this->dispatches->updateState($dispatchId, $data['state'], $request->user()), 'Estado del despacho actualizado.');
    }
}
