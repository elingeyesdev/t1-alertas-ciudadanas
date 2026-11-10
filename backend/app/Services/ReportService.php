<?php

namespace App\Services;

use App\Enums\StateReport;
use App\Events\NewReportCreated;
use App\Http\Resources\ReportCoincidenceResource;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\User;
use App\Support\SafeBroadcast;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** Relaciones que se cargan siempre para armar la respuesta */
    private const WITH = ['type', 'images.uploadedBy'];

    public function __construct(
        private readonly ImageService $images,
        private readonly NotificationService $notifications,
    ) {}

    public function create(array $data, ?UploadedFile $file): ReportResource
    {
        $user = User::find($data['userId']);
        if (! $user) {
            abort(404, 'Usuario no encontrado');
        }
        $type = ReportType::find($data['type']);
        if (! $type) {
            abort(404, 'Tipo no encontrado');
        }

        // Peso inicial: peso base del tipo (+2 si la descripción es detallada)
        $weight = $type->base_weight ?: 1;
        if (mb_strlen($data['description']) > 100) {
            $weight += 2;
        }

        $report = DB::transaction(function () use ($data, $user, $type, $weight, $file) {
            $report = Report::create([
                'description' => $data['description'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'weight' => $weight,
                'zone' => ($data['zone'] ?? null) ?: 'Zona desconocida',
                'expires_at' => now()->addHours(config('alertas.reports.expires_in_hours')),
                'status' => StateReport::Activo,
                'creator_id' => $user->id,
                'report_type_id' => $type->id,
            ]);
            if ($file) {
                $this->images->createFromReport($report, $user, $file);
            }

            return $report;
        });

        $report->load(self::WITH);

        $this->notifications->notifyNearbyUsers($report);

        $resource = new ReportResource($report);
        SafeBroadcast::send(new NewReportCreated($resource->resolve()));

        return $resource;
    }

    public function addImage(int $reportId, string $userId, UploadedFile $file): ReportResource
    {
        $report = Report::find($reportId);
        if (! $report) {
            abort(404, 'Reporte no encontrado');
        }
        $user = User::find($userId);
        if (! $user) {
            abort(404, 'Usuario no encontrado');
        }

        $this->images->createFromReport($report, $user, $file);
        $report->increment('weight'); // cada imagen extra suma 1 de peso

        return new ReportResource($report->fresh(self::WITH));
    }

    public function verifyReport(int $id): ReportResource
    {
        $report = Report::with('creator')->find($id);
        if (! $report) {
            abort(404, 'Reporte no encontrado');
        }

        $report->update(['verified' => true]);
        $this->notifications->notifyReportVerified($report);

        return new ReportResource($report->load(self::WITH));
    }

    public function findOne(int $id): ReportResource
    {
        $report = Report::with(self::WITH)->find($id);
        if (! $report) {
            abort(404, 'Reporte no encontrado');
        }

        return new ReportResource($report);
    }

    public function findByUserId(string $userId)
    {
        return ReportResource::collection(
            Report::with(self::WITH)->where('creator_id', $userId)->orderByDesc('id')->get()
        );
    }

    /**
     * Lista reportes. Filtros opcionales (los que envían la web y la app):
     * typeId, category, status (verified|pending), zone, search, from, to, includeDeleted.
     */
    public function findAll(array $filters = [])
    {
        $categories = [
            'accidentes' => '%accidente%',
            'robos' => '%robo%',
            'incendios' => '%incendio%',
            'emergencias' => '%emergencia%',
        ];

        $query = Report::with(self::WITH)
            ->when(filter_var($filters['includeDeleted'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->withTrashed())
            ->when($filters['typeId'] ?? null, fn ($q, $id) => $q->where('report_type_id', (int) $id))
            ->when($categories[$filters['category'] ?? ''] ?? null, fn ($q, $like) => $q->whereHas('type', fn ($t) => $t->where('name', 'ilike', $like)))
            ->when(($filters['status'] ?? null) === 'verified', fn ($q) => $q->where('verified', true))
            ->when(($filters['status'] ?? null) === 'pending', fn ($q) => $q->where('verified', false))
            ->when($filters['zone'] ?? null, fn ($q, $zone) => $q->where('zone', $zone))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('description', 'ilike', '%'.$s.'%')
                ->orWhere('zone', 'ilike', '%'.$s.'%')))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay()));

        return ReportResource::collection($query->orderByDesc('created_at')->get());
    }

    public function findAllWithDeleted()
    {
        return ReportResource::collection(
            Report::withTrashed()
                ->with([...self::WITH, 'dispatches.attendedBy.role', 'dispatches.attendedBy.authorityProfile'])
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function findNearby(float $latitude, float $longitude, float $radius)
    {
        $since = now()->subHours(config('alertas.reports.nearby_max_age_hours'));

        return ReportResource::collection(
            Report::with(self::WITH)
                ->withinRadius($latitude, $longitude, $radius)
                ->whereRaw('COALESCE(reports.updated_at, reports.created_at) >= ?', [$since])
                ->orderByDesc('created_at')
                ->get()
        );
    }

    /** Reportes del mismo tipo a ≤100 m creados en los últimos 200 minutos. */
    public function findCoincidences(float $latitude, float $longitude, int $type)
    {
        return ReportCoincidenceResource::collection(
            Report::with(self::WITH)
                ->withinRadius($latitude, $longitude, config('alertas.reports.similar_radius_m'))
                ->where('report_type_id', $type)
                ->where('created_at', '>=', now()->subMinutes(config('alertas.reports.similar_max_age_minutes')))
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function remove(int $id): array
    {
        return $this->closeReport($id, StateReport::Eliminado, 'Reporte eliminado correctamente');
    }

    public function resolveReport(int $id): array
    {
        return $this->closeReport($id, StateReport::Resuelto, 'Reporte resuelto correctamente');
    }

    public function reactivateReport(int $id): array
    {
        $report = Report::withTrashed()->find($id);
        if (! $report) {
            abort(404, "El reporte con ID {$id} no se encontro");
        }

        $report->status = StateReport::Activo;
        $report->expires_at = now()->addHours(config('alertas.reports.expires_in_hours'));
        $report->deleted_at = null;
        $report->save();

        return ['message' => 'Reporte reactivado correctamente'];
    }

    /** Cambia el estado y aplica soft delete (igual que en Nest). */
    private function closeReport(int $id, StateReport $status, string $message): array
    {
        $report = Report::find($id);
        if (! $report) {
            abort(404, "El reporte con ID {$id} no se encontro");
        }

        $report->status = $status;
        $report->save();
        $report->delete();

        return ['message' => $message];
    }
}
