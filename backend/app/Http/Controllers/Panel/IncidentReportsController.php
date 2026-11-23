<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StateReport;
use App\Http\Requests\IncidentReportFilters;
use App\Models\Report;
use App\Models\ReportType;
use App\Services\IncidentWorkbook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class IncidentReportsController extends PanelController
{
    public function index(IncidentReportFilters $request): View
    {
        $filters = $request->validated();
        $counts = $this->incidents($filters)->toBase()->selectRaw(
            'COUNT(*) AS total,
             COUNT(CASE WHEN status = ? THEN 1 END) AS active,
             COUNT(CASE WHEN status = ? THEN 1 END) AS resolved,
             COUNT(CASE WHEN verified THEN 1 END) AS verified',
            [StateReport::Activo->value, StateReport::Resuelto->value],
        )->first();

        return view('panel.reportes.analytics', [
            'filters' => $filters,
            'types' => ReportType::orderBy('name')->get(['id', 'name']),
            'stats' => array_map('intval', (array) $counts),
        ]);
    }

    public function excel(IncidentReportFilters $request, IncidentWorkbook $workbook): BinaryFileResponse
    {
        $filters = $request->validated();
        $path = tempnam(sys_get_temp_dir(), 'incidentes-');
        if ($path === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de incidentes.');
        }

        try {
            $workbook->write($path, $this->incidents($filters));

            clearstatcache(true, $path);

            return response()->download(
                $path,
                "incidentes-{$filters['desde']}_a_{$filters['hasta']}.xlsx",
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store',
                ],
            )->deleteFileAfterSend();
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }
    }

    private function incidents(array $filters): Builder
    {
        $timezone = config('alertas.display_timezone');
        $from = CarbonImmutable::parse($filters['desde'], $timezone)->startOfDay();
        $until = CarbonImmutable::parse($filters['hasta'], $timezone)->addDay()->startOfDay();

        return Report::withTrashed()
            ->where('status', '!=', StateReport::Eliminado->value)
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $until->utc())
            ->when($filters['tipo'] ?? null, fn (Builder $query, $type) => $query->where('report_type_id', $type));
    }
}
