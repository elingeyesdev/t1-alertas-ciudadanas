<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StateReport;
use App\Http\Requests\IncidentReportFilters;
use App\Models\Report;
use App\Models\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
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

    public function excel(IncidentReportFilters $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $timezone = config('alertas.display_timezone');
        $incidents = $this->incidents($filters)
            ->select('id', 'created_at', 'report_type_id', 'status', 'verified')
            ->with('type:id,name')
            ->lazyById();

        $path = tempnam(sys_get_temp_dir(), 'incidentes-');
        if ($path === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de incidentes.');
        }

        try {
            $writer = new Writer;
            $writer->openToFile($path);
            try {
                $writer->getCurrentSheet()->setName('Incidentes');
                $writer->addRow(Row::fromValues(['Fecha', 'Tipo', 'Estado', 'Verificado']));

                foreach ($incidents as $incident) {
                    $writer->addRow(new Row([
                        new StringCell($incident->created_at->timezone($timezone)->toDateString(), null),
                        new StringCell($incident->type->name, null),
                        new StringCell($incident->status->value, null),
                        new StringCell($incident->verified ? 'Sí' : 'No', null),
                    ]));
                }
            } finally {
                $writer->close();
            }

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
