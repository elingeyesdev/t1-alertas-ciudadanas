<?php

namespace Tests\Feature;

use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\User;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class PanelIncidentReportsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'Incidente', 'Fecha', 'Tipo', 'Estado', 'Verificación oficial', 'Votos de confirmación',
        'Votos de rechazo', 'Imágenes', 'Videos', 'Total de evidencias', 'Comentarios', 'Respuestas',
        'Autoridades asignadas', 'Despachos', 'SOS',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ReportTypeSeeder::class]);
        config(['alertas.display_timezone' => 'America/La_Paz']);
        $this->withoutVite();
    }

    public function test_only_an_administrator_can_access_the_reports_and_export(): void
    {
        $routes = ['panel.reportes.index', 'panel.reportes.excel'];
        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('panel.login'));
        }

        foreach ([1, 2] as $role) {
            $this->actingAs(User::factory()->role($role)->create(), 'web');
            foreach ($routes as $route) {
                $this->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_filters_match_the_kpis_and_the_excel_rows(): void
    {
        $citizen = User::factory()->role(1)->create();
        $type = ReportType::findOrFail(1);
        $type->update(['name' => '=1+1']);
        $this->incident($citizen, '2026-11-19 03:59:59');
        $incident = $this->incident($citizen, '2026-11-19 04:00:00', ['verified' => true, 'is_sos' => true]);
        $date = '2026-11-19 12:00:00';
        $incident->images()->create(['cloudinary_id' => 'demo', 'url' => 'https://example.test/privado',
            'uploaded_by' => $citizen->id, 'uploaded_at' => $date]);
        $incident->videos()->forceCreate(['path' => 'videos/privado.mp4', 'uploaded_by' => $citizen->id, 'uploaded_at' => $date]);
        $comment = $incident->comments()->create(['text' => 'Texto privado', 'creator_id' => $citizen->id]);
        $reply = $incident->comments()->create([
            'text' => 'Respuesta privada', 'creator_id' => $citizen->id, 'parent_comment_id' => $comment->id,
        ]);
        $comment->forceFill(['created_at' => $date])->save();
        $reply->forceFill(['created_at' => $date])->save();

        $authority = User::factory()->role(2)->create(['first_name' => 'Nombre privado']);
        $authority->authorityProfile()->create(['ci' => '1234567', 'gmail' => 'privado@example.test', 'profile_type' => 'bombero']);
        $station = EmergencyStation::create([
            'name' => 'Estación Norte', 'installation_type' => 'bombero', 'latitude' => -17.8, 'longitude' => -63.1,
        ]);
        $dispatch = $incident->dispatches()->create([
            'destination_id' => $station->id, 'attended_by' => $authority->id, 'state' => 'en_curso', 'recorded_at' => $date,
        ]);
        $event = $incident->caseEvents()->create(['type' => 'despachado', 'detail' => 'Detalle privado', 'dispatch_id' => $dispatch->id]);
        $event->forceFill(['created_at' => $date])->save();

        $voters = User::factory()->count(2)->role(1)->create();
        DB::table('report_votes')->insert([
            ['report_id' => $incident->id, 'user_id' => $voters[0]->id, 'vote' => 'seen'],
            ['report_id' => $incident->id, 'user_id' => $voters[1]->id, 'vote' => 'false'],
        ]);
        $this->incident($citizen, '2026-11-20 03:59:59', ['status' => 'resuelto'])->delete();
        $this->incident($citizen, '2026-11-19 18:00:00', ['status' => 'vencido'])->delete();
        $this->incident($citizen, '2026-11-19 18:00:00', ['status' => 'eliminado'])->delete();
        $this->incident($citizen, '2026-11-19 18:00:00', ['report_type_id' => 2]);
        $this->incident($citizen, '2026-11-20 04:00:00');
        $this->actingAs(User::factory()->role(3)->create(), 'web');
        $filters = ['desde' => '2026-11-19', 'hasta' => '2026-11-19', 'tipo' => $type->id];

        $this->get(route('panel.reportes.index', $filters))
            ->assertOk()
            ->assertSee('Descargar Excel')
            ->assertViewHas('stats', ['total' => 3, 'active' => 1, 'resolved' => 1, 'verified' => 1]);

        $this->get(route('panel.reportes.index', array_diff_key($filters, ['tipo' => true])))
            ->assertViewHas('stats', ['total' => 4, 'active' => 2, 'resolved' => 1, 'verified' => 1]);

        $response = $this->get(route('panel.reportes.excel', $filters))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('incidentes-2026-11-19_a_2026-11-19.xlsx');

        $workbook = $this->readWorkbook($response);
        $this->assertSame([
            self::HEADERS,
            [1, '2026-11-19', '=1+1', 'activo', 'Confirmado', 1, 1, 1, 1, 2, 1, 1, 1, 1, 'Sí'],
            [2, '2026-11-19', '=1+1', 'resuelto', 'Sin confirmación oficial', 0, 0, 0, 0, 0, 0, 0, 0, 0, 'No'],
            [3, '2026-11-19', '=1+1', 'vencido', 'Sin confirmación oficial', 0, 0, 0, 0, 0, 0, 0, 0, 0, 'No'],
        ], $workbook['Incidentes']);
        $this->assertSame([
            ['Incidente', 'Autoridad', 'Perfil', 'Estación', 'Estado', 'Fecha de asignación', 'Fecha de salida'],
            [1, 'Autoridad 1', 'bombero', 'Estación Norte', 'en_curso', '2026-11-19', ''],
        ], $workbook['Despachos']);
        $this->assertSame([
            ['Incidente', 'Fecha', 'Clase'], [1, '2026-11-19', 'Comentario'], [1, '2026-11-19', 'Respuesta'],
        ], $workbook['Comentarios']);
        $this->assertSame([
            ['Incidente', 'Fecha', 'Evento'], [1, '2026-11-19', 'Unidad asignada'],
        ], $workbook['Seguimiento']);
    }

    public function test_empty_results_use_the_default_local_range_and_export_the_headers(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 02:00:00 UTC'));
        $this->actingAs(User::factory()->role(3)->create(), 'web');

        $this->get(route('panel.reportes.index'))
            ->assertOk()
            ->assertViewHas('filters', ['desde' => '2026-10-24', 'hasta' => '2026-11-22'])
            ->assertViewHas('stats', ['total' => 0, 'active' => 0, 'resolved' => 0, 'verified' => 0])
            ->assertSee('No hay incidentes para los filtros aplicados.');

        $response = $this->get(route('panel.reportes.excel'))
            ->assertOk()
            ->assertDownload('incidentes-2026-10-24_a_2026-11-22.xlsx');
        $workbook = $this->readWorkbook($response);
        $this->assertSame(['Incidentes', 'Despachos', 'Comentarios', 'Seguimiento'], array_keys($workbook));
        $this->assertSame([self::HEADERS], $workbook['Incidentes']);
        $this->assertSame([1, 1, 1, 1], array_values(array_map('count', $workbook)));
    }

    public function test_both_routes_reject_invalid_filters(): void
    {
        $this->actingAs(User::factory()->role(3)->create(), 'web');

        foreach (['panel.reportes.index', 'panel.reportes.excel'] as $route) {
            $this->get(route($route, ['desde' => '2026-11-19']))
                ->assertRedirect(route('panel.reportes.index'))
                ->assertSessionHasErrors('hasta');
            $this->get(route($route, ['desde' => '2026-02-30', 'hasta' => '2026-11-19']))
                ->assertSessionHasErrors('desde');
            $this->get(route($route, ['desde' => '2026-11-20', 'hasta' => '2026-11-19', 'tipo' => 999999]))
                ->assertSessionHasErrors(['hasta', 'tipo']);
        }
    }

    private function incident(User $citizen, string $createdAt, array $attributes = []): Report
    {
        $incident = new Report(array_replace([
            'description' => 'Texto privado del reportante',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'weight' => 3,
            'zone' => 'Centro',
            'status' => 'activo',
            'expires_at' => '2026-11-22 00:00:00',
            'creator_id' => $citizen->id,
            'report_type_id' => 1,
        ], $attributes));
        $incident->created_at = $createdAt;
        $incident->save();

        return $incident;
    }

    private function readWorkbook(TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);

        try {
            $workbook = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                $workbook[$sheet->getName()] = $rows;
            }

            return $workbook;
        } finally {
            $reader->close();
            unlink($path);
        }
    }
}
