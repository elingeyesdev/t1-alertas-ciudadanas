<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SosService;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU-24: el SOS sale destacado en el panel web. */
class SosPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_highlights_active_sos(): void
    {
        $this->seed([RoleSeeder::class, ReportTypeSeeder::class]);
        $citizen = User::factory()->role(1)->create(['first_name' => 'Lucía', 'last_name' => 'Paz']);
        $admin = User::factory()->role(3)->create();
        $sos = app(SosService::class)->trigger($citizen, ['latitude' => -17.784, 'longitude' => -63.182, 'zone' => 'Equipetrol']);

        $this->actingAs($admin, 'web');
        $this->get('/panel')->assertOk()
            ->assertSee('Botón de pánico activo')
            ->assertSee('Lucía Paz')
            ->assertSee('Sin unidad asignada');
        $this->get('/panel/alertas')->assertOk()->assertSee('ti-sos', false);
        $this->getJson('/panel/mapa/datos')->assertOk()
            ->assertJsonPath('reports.0.id', $sos['report']->id)
            ->assertJsonPath('reports.0.is_sos', true);
    }
}
