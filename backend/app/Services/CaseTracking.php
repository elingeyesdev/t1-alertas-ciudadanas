<?php

namespace App\Services;

use App\Enums\StateReport;
use App\Enums\StateType;
use App\Models\CaseEvent;
use App\Models\Dispatch;
use App\Models\Report;
use App\Models\User;
use App\Support\Dt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HU-26 Seguimiento del caso: registra cada paso del reporte en la línea de
 * tiempo y avisa por push a quien lo creó (verificado, unidad asignada,
 * en camino, completado...).
 */
class CaseTracking
{
    public const CREATED = 'creado';
    public const VERIFIED = 'verificado';
    public const DISPATCHED = 'despachado';
    public const EN_ROUTE = 'en_camino';
    public const COMPLETED = 'completado';
    public const CANCELLED = 'cancelado';
    public const RESOLVED = 'resuelto';

    /** Título que ve el ciudadano en la app y en el push. */
    private const TITLES = [
        self::CREATED => 'Reporte recibido',
        self::VERIFIED => 'Reporte verificado',
        self::DISPATCHED => 'Unidad asignada',
        self::EN_ROUTE => 'Unidad en camino',
        self::COMPLETED => 'Atención completada',
        self::CANCELLED => 'Despacho cancelado',
        self::RESOLVED => 'Caso resuelto',
    ];

    public function __construct(private readonly FcmService $fcm) {}

    public static function title(string $type): string
    {
        return self::TITLES[$type] ?? ucfirst($type);
    }

    /**
     * Guarda un paso del caso y, si corresponde, avisa al creador.
     * Nunca rompe la operación principal: un fallo aquí solo se registra en el log.
     */
    public function record(Report $report, string $type, ?Dispatch $dispatch = null, ?User $actor = null, bool $notify = true): void
    {
        try {
            $detail = $this->detailFor($report, $type, $dispatch);
            CaseEvent::create([
                'report_id' => $report->id,
                'dispatch_id' => $dispatch?->id,
                'type' => $type,
                'detail' => $detail,
            ]);

            if ($notify && $type !== self::CREATED) {
                $this->notifyCreator($report, $type, $detail, $actor);
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar el paso del caso.', [
                'report_id' => $report->id, 'type' => $type, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * La unidad asignada salió hacia el incidente. Solo cuenta la primera vez
     * y solo si quien sale es la autoridad asignada a un despacho en curso.
     */
    public function markEnRoute(Dispatch $dispatch, User $unit): bool
    {
        if ($dispatch->attended_by !== $unit->id || $dispatch->state !== StateType::EnCurso) {
            return false;
        }

        $updated = Dispatch::whereKey($dispatch->id)->whereNull('departed_at')
            ->update(['departed_at' => now()]);
        if (! $updated) {
            return false;
        }

        $dispatch->refresh();
        if ($report = $dispatch->report) {
            $this->record($report, self::EN_ROUTE, $dispatch, $unit);
        }

        return true;
    }

    /** Busca el despacho en curso de esta unidad para el reporte y lo marca «en camino». */
    public function markEnRouteForReport(int $reportId, User $unit): bool
    {
        $dispatch = Dispatch::where('report_id', $reportId)
            ->where('attended_by', $unit->id)
            ->where('state', StateType::EnCurso->value)
            ->whereNull('departed_at')
            ->latest('id')
            ->first();

        return $dispatch ? $this->markEnRoute($dispatch, $unit) : false;
    }

    /** Datos del caso para la app: estado actual, línea de tiempo y despachos. */
    public function summary(Report $report): array
    {
        $report->loadMissing(['type', 'dispatches.attendedBy.authorityProfile', 'dispatches.destination']);
        $events = CaseEvent::where('report_id', $report->id)->orderBy('id')->get();

        $timeline = $events->map(fn (CaseEvent $e) => [
            'type' => $e->type,
            'title' => self::title($e->type),
            'detail' => $e->detail,
            'dispatch_id' => $e->dispatch_id,
            'at' => Dt::iso($e->created_at),
        ])->all();

        // Reportes creados antes de esta función: se arma lo que se puede con los datos que ya existen.
        $has = fn (string $type, ?int $dispatchId = null) => $events->contains(
            fn (CaseEvent $e) => $e->type === $type && ($dispatchId === null || $e->dispatch_id === $dispatchId)
        );
        if (! $has(self::CREATED)) {
            array_unshift($timeline, [
                'type' => self::CREATED, 'title' => self::title(self::CREATED),
                'detail' => $this->detailFor($report, self::CREATED), 'dispatch_id' => null,
                'at' => Dt::iso($report->created_at),
            ]);
        }
        foreach ($report->dispatches as $dispatch) {
            if (! $has(self::DISPATCHED, $dispatch->id)) {
                $timeline[] = [
                    'type' => self::DISPATCHED, 'title' => self::title(self::DISPATCHED),
                    'detail' => $this->detailFor($report, self::DISPATCHED, $dispatch), 'dispatch_id' => $dispatch->id,
                    'at' => Dt::iso($dispatch->recorded_at),
                ];
            }
        }
        usort($timeline, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        return [
            'report' => [
                'id' => $report->id,
                'type' => $report->type?->name,
                'description' => $report->description,
                'zone' => $report->zone,
                'status' => $report->status?->value,
                'verified' => (bool) $report->verified,
                'created_at' => Dt::iso($report->created_at),
            ],
            'stage' => $this->stage($report),
            'timeline' => array_values($timeline),
            'dispatches' => $report->dispatches->sortByDesc('id')->values()->map(fn (Dispatch $d) => [
                'id' => $d->id,
                'state' => $d->state?->value,
                'assigned_at' => Dt::iso($d->recorded_at),
                'departed_at' => Dt::iso($d->departed_at),
                'unit' => [
                    'name' => trim(($d->attendedBy?->first_name ?? '').' '.($d->attendedBy?->last_name ?? '')),
                    'profile_type' => $d->attendedBy?->authorityProfile?->profile_type?->value,
                ],
                'station' => $d->destination ? [
                    'name' => $d->destination->name,
                    'type' => $d->destination->installation_type?->value,
                ] : null,
            ])->all(),
        ];
    }

    /** Paso actual del caso, del más avanzado al más básico. */
    public function stage(Report $report): string
    {
        if ($report->status === StateReport::Resuelto) {
            return self::RESOLVED;
        }

        $dispatches = $report->relationLoaded('dispatches') ? $report->dispatches : $report->dispatches()->get();
        $active = $dispatches->where('state', StateType::EnCurso)->sortByDesc('id')->first();
        if ($active) {
            return $active->departed_at ? self::EN_ROUTE : self::DISPATCHED;
        }
        if ($dispatches->contains(fn ($d) => $d->state === StateType::Completado)) {
            return self::COMPLETED;
        }

        return $report->verified ? self::VERIFIED : self::CREATED;
    }

    private function detailFor(Report $report, string $type, ?Dispatch $dispatch = null): string
    {
        $unit = $dispatch ? $this->unitLabel($dispatch) : 'La unidad';

        return match ($type) {
            self::CREATED => "Recibimos tu reporte #{$report->id}.",
            self::VERIFIED => 'Una autoridad confirmó que el incidente es real.',
            self::DISPATCHED => "{$unit} fue asignada a tu caso.",
            self::EN_ROUTE => "{$unit} va en camino al lugar.",
            self::COMPLETED => "{$unit} terminó la atención.",
            self::CANCELLED => 'El despacho se canceló. Si sigues en peligro, vuelve a pedir ayuda.',
            self::RESOLVED => 'El caso se marcó como resuelto.',
            default => '',
        };
    }

    private function unitLabel(Dispatch $dispatch): string
    {
        $dispatch->loadMissing(['attendedBy.authorityProfile', 'destination']);
        $kind = match ($dispatch->attendedBy?->authorityProfile?->profile_type?->value) {
            'paramedico' => 'Una ambulancia',
            'bombero' => 'Una unidad de bomberos',
            'policia' => 'Una patrulla',
            default => 'Una unidad',
        };
        $station = $dispatch->destination?->name;

        return $station ? "{$kind} de {$station}" : $kind;
    }

    private function notifyCreator(Report $report, string $type, string $detail, ?User $actor): void
    {
        $creator = $report->creator()->first();
        if (! $creator || $creator->trashed() || blank($creator->fcm_token) || $creator->id === $actor?->id) {
            return;
        }

        $this->fcm->sendToTokens([$creator->fcm_token], self::title($type), $detail, [
            'type' => 'case_update',
            'reportId' => (string) $report->id,
            'event' => $type,
        ]);
    }
}
