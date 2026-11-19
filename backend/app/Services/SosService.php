<?php

namespace App\Services;

use App\Enums\StateReport;
use App\Enums\StateType;
use App\Events\NewReportCreated;
use App\Http\Resources\ReportResource;
use App\Jobs\NotifySosAssignment;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\User;
use App\Support\Geo;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HU-24 Botón de pánico (SOS).
 * Crea una alerta prioritaria SIN pasar por verificación, asigna al instante
 * la autoridad disponible más cercana y avisa al círculo de confianza (HU-25).
 */
class SosService
{
    public const TYPE_NAME = 'Botón de pánico (SOS)';

    /** Si la persona vuelve a pulsar SOS en estos minutos, se reutiliza la alerta abierta. */
    public const REPEAT_WINDOW_MINUTES = 15;

    /** Tipo de emergencia → perfil de autoridad y tipo de estación preferidos. */
    private const KINDS = [
        'general' => ['label' => 'Emergencia', 'profile' => 'policia', 'station' => 'policia'],
        'seguridad' => ['label' => 'Robo o agresión', 'profile' => 'policia', 'station' => 'policia'],
        'medica' => ['label' => 'Emergencia médica', 'profile' => 'paramedico', 'station' => 'hospital'],
        'incendio' => ['label' => 'Incendio', 'profile' => 'bombero', 'station' => 'bombero'],
    ];

    public function __construct(
        private readonly DispatchService $dispatches,
        private readonly CaseTracking $cases,
        private readonly TrustCircle $circle,
    ) {}

    public static function kinds(): array
    {
        return array_keys(self::KINDS);
    }

    /** @return array{report: ReportResource, existing: bool, dispatch: ?array, circle: array} */
    public function trigger(User $user, array $data): array
    {
        $kind = $data['kind'] ?? 'general';
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];

        $existing = Report::where('creator_id', $user->id)
            ->where('is_sos', true)
            ->where('status', StateReport::Activo->value)
            ->where('created_at', '>=', now()->subMinutes(self::REPEAT_WINDOW_MINUTES))
            ->latest('id')
            ->first();

        if ($existing) {
            // Pulsó otra vez: no se duplica la alerta, solo se reenvían los enlaces de WhatsApp.
            $report = $existing;
            $circle = $this->circle->alert($user, $report->latitude, $report->longitude, $report->id, notifyApp: false);
        } else {
            $report = $this->createReport($user, $kind, $lat, $lng, $data);
            $this->assignNearestUnit($report, $kind);
            $circle = $this->circle->alert($user, $lat, $lng, $report->id);
        }

        $report->load(['type', 'images.uploadedBy']);
        $dispatch = Dispatch::with(['attendedBy.authorityProfile', 'destination'])
            ->where('report_id', $report->id)->where('state', StateType::EnCurso->value)->latest('id')->first();

        return [
            'report' => new ReportResource($report),
            'existing' => (bool) $existing,
            'dispatch' => $dispatch ? [
                'id' => $dispatch->id,
                'profile_type' => $dispatch->attendedBy?->authorityProfile?->profile_type?->value,
                'station' => $dispatch->destination?->name,
                'departed_at' => $dispatch->departed_at?->toISOString(),
            ] : null,
            'circle' => $circle,
        ];
    }

    private function createReport(User $user, string $kind, float $lat, float $lng, array $data): Report
    {
        $type = ReportType::firstOrCreate(['name' => self::TYPE_NAME], ['base_weight' => 10]);
        $label = self::KINDS[$kind]['label'];
        $note = trim((string) ($data['note'] ?? ''));

        $report = Report::create([
            'description' => "🚨 SOS — {$label}".($note !== '' ? ": {$note}" : '. Pedido con el botón de pánico.'),
            'latitude' => $lat,
            'longitude' => $lng,
            'weight' => 10,
            'zone' => ($data['zone'] ?? null) ?: 'Zona desconocida',
            'expires_at' => now()->addHours(config('alertas.reports.expires_in_hours')),
            'status' => StateReport::Activo,
            'creator_id' => $user->id,
            'report_type_id' => $type->id,
            'is_sos' => true,
        ]);

        $this->cases->record($report, CaseTracking::CREATED, notify: false);
        SafeBroadcast::send(new NewReportCreated((new ReportResource($report->load('type')))->resolve()));

        return $report;
    }

    /**
     * Sin verificación: elige la autoridad libre más cercana (primero las del
     * perfil que corresponde al tipo de emergencia) y crea el despacho al instante.
     */
    public function assignNearestUnit(Report $report, string $kind): ?Dispatch
    {
        $prefs = self::KINDS[$kind] ?? self::KINDS['general'];

        try {
            return DB::transaction(function () use ($report, $prefs) {
                $unit = $this->nearestAvailableUnit($report, $prefs['profile']);
                $station = $this->nearestStation($report, $unit?->authorityProfile?->profile_type?->value, $prefs['station']);
                if (! $unit || ! $station) {
                    Log::warning('SOS sin unidad disponible: queda destacado en el panel para asignar a mano.', ['report_id' => $report->id]);

                    return null;
                }

                $this->dispatches->create($report->id, $station->id, $unit->id);
                $dispatch = Dispatch::where('report_id', $report->id)->latest('id')->first();
                NotifySosAssignment::dispatch($dispatch->id)
                    ->onConnection(config('alertas.ambulance.queue_connection'))->onQueue('ambulance')->afterCommit();

                return $dispatch;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo asignar una unidad al SOS.', ['report_id' => $report->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function nearestAvailableUnit(Report $report, string $preferredProfile): ?User
    {
        $units = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'autoridad'))
            ->whereHas('authorityProfile')
            ->whereDoesntHave('dispatches', fn ($q) => $q->where('state', StateType::EnCurso->value))
            ->with('authorityProfile')
            ->lockForUpdate()
            ->get();

        return $units->sortBy([
            // 1) el perfil adecuado primero, 2) los que tienen ubicación conocida, 3) los más cercanos
            fn (User $a, User $b) => ($a->authorityProfile?->profile_type?->value !== $preferredProfile) <=> ($b->authorityProfile?->profile_type?->value !== $preferredProfile),
            fn (User $a, User $b) => $this->distanceTo($report, $a) <=> $this->distanceTo($report, $b),
        ])->first();
    }

    private function distanceTo(Report $report, User $unit): float
    {
        if ($unit->last_latitude === null || $unit->last_longitude === null) {
            return INF;
        }

        return Geo::distance($report->latitude, $report->longitude, $unit->last_latitude, $unit->last_longitude);
    }

    private function nearestStation(Report $report, ?string $unitProfile, string $preferredType): ?EmergencyStation
    {
        $type = match ($unitProfile) {
            'paramedico' => 'hospital',
            'bombero' => 'bombero',
            'policia' => 'policia',
            default => $preferredType,
        };

        return EmergencyStation::all()->sortBy([
            fn ($a, $b) => ($a->installation_type?->value !== $type) <=> ($b->installation_type?->value !== $type),
            fn ($a, $b) => Geo::distance($report->latitude, $report->longitude, $a->latitude, $a->longitude)
                <=> Geo::distance($report->latitude, $report->longitude, $b->latitude, $b->longitude),
        ])->first();
    }
}
