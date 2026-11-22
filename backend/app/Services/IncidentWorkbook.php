<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class IncidentWorkbook
{
    private const HEADERS = [
        'Incidentes' => [
            'Incidente', 'Fecha', 'Tipo', 'Estado', 'Verificación oficial', 'Votos de confirmación',
            'Votos de rechazo', 'Imágenes', 'Videos', 'Total de evidencias', 'Comentarios', 'Respuestas',
            'Autoridades asignadas', 'Despachos', 'SOS',
        ],
        'Despachos' => ['Incidente', 'Autoridad', 'Perfil', 'Estación', 'Estado', 'Fecha de asignación', 'Fecha de salida'],
        'Comentarios' => ['Incidente', 'Fecha', 'Clase'],
        'Seguimiento' => ['Incidente', 'Fecha', 'Evento'],
    ];

    public function write(string $path, Builder $query): void
    {
        $timezone = config('alertas.display_timezone');
        $incidents = $query
            ->select('id', 'created_at', 'report_type_id', 'status', 'verified', 'is_sos')
            ->withCount(['images', 'videos'])
            ->addSelect([
                'seen_votes' => DB::table('report_votes')->selectRaw('count(*)')
                    ->whereColumn('report_id', 'reports.id')->where('vote', 'seen'),
                'false_votes' => DB::table('report_votes')->selectRaw('count(*)')
                    ->whereColumn('report_id', 'reports.id')->where('vote', 'false'),
            ])
            ->with([
                'type:id,name',
                'comments:id,report_id,parent_comment_id,created_at',
                'caseEvents:id,report_id,type,created_at',
                'dispatches:id,report_id,destination_id,attended_by,state,recorded_at,departed_at',
                'dispatches.attendedBy.authorityProfile:id,user_id,profile_type',
                'dispatches.attendedBy:id',
                'dispatches.destination:id,name',
            ])
            ->lazyById(200);

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $sheets = [];
            foreach (self::HEADERS as $name => $headers) {
                $sheet = $sheets === [] ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($name);
                $writer->addRow(Row::fromValues($headers));
                $sheets[$name] = $sheet;
            }

            $authorityCodes = [];
            foreach ($incidents as $index => $incident) {
                $key = $index + 1;
                $replies = $incident->comments->whereNotNull('parent_comment_id')->count();
                $authorities = $incident->dispatches->pluck('attended_by')->filter()->unique()->count();
                $writer->setCurrentSheet($sheets['Incidentes']);
                $this->addRow($writer, [
                    $key,
                    $this->day($incident->created_at, $timezone),
                    $incident->type->name,
                    $incident->status->value,
                    $incident->verified ? 'Confirmado' : 'Sin confirmación oficial',
                    (int) $incident->seen_votes,
                    (int) $incident->false_votes,
                    $incident->images_count,
                    $incident->videos_count,
                    $incident->images_count + $incident->videos_count,
                    $incident->comments->count() - $replies,
                    $replies,
                    $authorities,
                    $incident->dispatches->count(),
                    $incident->is_sos ? 'Sí' : 'No',
                ]);

                $writer->setCurrentSheet($sheets['Despachos']);
                foreach ($incident->dispatches->sortBy('id') as $dispatch) {
                    $code = null;
                    if ($dispatch->attended_by !== null) {
                        $authorityCodes[$dispatch->attended_by] ??= count($authorityCodes) + 1;
                        $code = 'Autoridad '.$authorityCodes[$dispatch->attended_by];
                    }
                    $this->addRow($writer, [
                        $key, $code, $dispatch->attendedBy?->authorityProfile?->profile_type?->value,
                        $dispatch->destination?->name, $dispatch->state->value,
                        $this->day($dispatch->recorded_at, $timezone), $this->day($dispatch->departed_at, $timezone),
                    ]);
                }

                $writer->setCurrentSheet($sheets['Comentarios']);
                foreach ($incident->comments->sortBy('id') as $comment) {
                    $this->addRow($writer, [
                        $key, $this->day($comment->created_at, $timezone),
                        $comment->parent_comment_id === null ? 'Comentario' : 'Respuesta',
                    ]);
                }

                $writer->setCurrentSheet($sheets['Seguimiento']);
                foreach ($incident->caseEvents as $event) {
                    $this->addRow($writer, [
                        $key, $this->day($event->created_at, $timezone), CaseTracking::title($event->type),
                    ]);
                }
            }
        } finally {
            $writer->close();
        }
    }

    private function day(?CarbonInterface $date, string $timezone): ?string
    {
        return $date?->copy()->timezone($timezone)->toDateString();
    }

    private function addRow(Writer $writer, array $values): void
    {
        $writer->addRow(new Row(array_map(
            fn ($value) => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value),
            $values,
        )));
    }
}
