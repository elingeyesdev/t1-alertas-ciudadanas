<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportType;
use App\Models\User;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class PanelIncidentReportsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['Fecha', 'Tipo', 'Estado', 'Verificado'];

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
        $this->incident($citizen, '2026-11-19 04:00:00', ['verified' => true]);
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

        $this->assertSame([
            self::HEADERS,
            ['2026-11-19', '=1+1', 'activo', 'Sí'],
            ['2026-11-19', '=1+1', 'resuelto', 'No'],
            ['2026-11-19', '=1+1', 'vencido', 'No'],
        ], $this->readWorkbook($response));
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
        $this->assertSame([self::HEADERS], $this->readWorkbook($response));
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
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $this->assertSame('Incidentes', $sheet->getName());
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }

            return $rows;
        } finally {
            $reader->close();
            unlink($path);
        }
    }
}
