<?php

namespace Tests\Feature;

use App\Models\Alerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAlertasTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_tabla_con_fecha_tipo_y_estado(): void
    {
        Alerta::factory()->create([
            'tipo' => 'robo',
            'estado' => 'pendiente',
            'fecha' => '2026-10-15 14:30:00',
        ]);

        $this->get('/alertas')
            ->assertOk()
            ->assertSeeInOrder(['Fecha', 'Tipo', 'Estado'])
            ->assertSeeInOrder(['15/10/2026 14:30', 'Robo', 'Pendiente']);
    }

    public function test_permite_filtrar_por_tipo_de_alerta(): void
    {
        Alerta::factory()->create(['tipo' => 'incendio', 'descripcion' => 'Fuego en mercado']);
        Alerta::factory()->create(['tipo' => 'robo', 'descripcion' => 'Robo de celular']);

        $this->get('/alertas?tipo=incendio')
            ->assertOk()
            ->assertSee('Fuego en mercado')
            ->assertDontSee('Robo de celular');
    }

    public function test_rechaza_un_tipo_de_alerta_invalido(): void
    {
        $this->get('/alertas?tipo=inexistente')->assertSessionHasErrors('tipo');
    }

    public function test_ordena_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        Alerta::factory()->create(['descripcion' => 'Alerta antigua', 'fecha' => now()->subDays(3)]);
        Alerta::factory()->create(['descripcion' => 'Alerta reciente', 'fecha' => now()]);
        Alerta::factory()->create(['descripcion' => 'Alerta intermedia', 'fecha' => now()->subDay()]);

        $this->get('/alertas')
            ->assertOk()
            ->assertSeeInOrder(['Alerta reciente', 'Alerta intermedia', 'Alerta antigua']);
    }
}
