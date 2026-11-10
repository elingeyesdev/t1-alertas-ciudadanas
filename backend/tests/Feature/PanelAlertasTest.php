<?php

namespace Tests\Feature;

use App\Enums\StateReport;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pruebas del panel /alertas (antes en web/, ahora sobre los reportes reales). */
class PanelAlertasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ReportTypeSeeder::class]);
        $this->user = User::factory()->create();
    }

    private function reporte(array $attrs = []): Report
    {
        $report = Report::create(array_merge([
            'description' => 'Reporte de prueba',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'weight' => 5,
            'expires_at' => now()->addHours(2),
            'status' => StateReport::Activo,
            'creator_id' => $this->user->id,
            'report_type_id' => 2,
        ], array_diff_key($attrs, ['created_at' => 1])));

        if (isset($attrs['created_at'])) {
            $report->created_at = $attrs['created_at'];
            $report->save();
        }

        return $report;
    }

    public function test_muestra_tabla_con_fecha_tipo_y_estado(): void
    {
        // 18:30 UTC = 14:30 en Bolivia (America/La_Paz)
        $this->reporte(['report_type_id' => 2, 'created_at' => '2026-10-15 18:30:00']);

        $this->get('/alertas')
            ->assertOk()
            ->assertSeeInOrder(['Fecha', 'Tipo', 'Estado'])
            ->assertSeeInOrder(['15/10/2026 14:30', 'Robo agravado', 'Activo']);
    }

    public function test_permite_filtrar_por_tipo_de_alerta(): void
    {
        $this->reporte(['report_type_id' => 3, 'description' => 'Fuego en mercado']);
        $this->reporte(['report_type_id' => 6, 'description' => 'Robo de celular']);

        $this->get('/alertas?tipo=3')
            ->assertOk()
            ->assertSee('Fuego en mercado')
            ->assertDontSee('Robo de celular');
    }

    public function test_rechaza_un_tipo_de_alerta_invalido(): void
    {
        $this->get('/alertas?tipo=999')->assertSessionHasErrors('tipo');
    }

    public function test_ordena_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $this->reporte(['description' => 'Alerta antigua', 'created_at' => now()->subDays(3)]);
        $this->reporte(['description' => 'Alerta reciente', 'created_at' => now()]);
        $this->reporte(['description' => 'Alerta intermedia', 'created_at' => now()->subDay()]);

        $this->get('/alertas')
            ->assertOk()
            ->assertSeeInOrder(['Alerta reciente', 'Alerta intermedia', 'Alerta antigua']);
    }

    public function test_incluye_reportes_resueltos_y_marca_verificados(): void
    {
        $r = $this->reporte(['description' => 'Ya atendido', 'verified' => true, 'status' => StateReport::Resuelto]);
        $r->delete();

        $this->get('/alertas')->assertOk()->assertSee('Ya atendido')->assertSee('Resuelto')->assertSee('Verificado');
    }
}
