<?php

namespace Tests\Feature;

use App\Mail\AuthorityCredentialsMail;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Módulos del panel Blade/Tabler: inicio, mapa, alertas, usuarios y estaciones. */
class PanelModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ReportTypeSeeder::class]);
        Storage::fake('public');
        $this->admin = User::factory()->role(3)->create(['first_name' => 'Ana', 'last_name' => 'Admin']);
    }

    private function report(array $attrs = []): Report
    {
        $citizen = User::factory()->role(1)->create();

        return Report::create(array_merge([
            'description' => 'Robo en la avenida',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'weight' => 3,
            'zone' => 'Centro',
            'expires_at' => now()->addHours(2),
            'status' => 'activo',
            'creator_id' => $citizen->id,
            'report_type_id' => 1,
        ], $attrs));
    }

    private function station(array $attrs = []): EmergencyStation
    {
        return EmergencyStation::create(array_merge([
            'name' => 'EPI 1', 'installation_type' => 'policia', 'latitude' => -17.78, 'longitude' => -63.18,
        ], $attrs));
    }

    public function test_every_page_requires_an_admin_session(): void
    {
        foreach (['/panel', '/panel/mapa', '/panel/alertas', '/panel/usuarios', '/panel/estaciones'] as $url) {
            $this->get($url)->assertRedirect(route('panel.login'));
        }

        $authority = User::factory()->role(2)->create();
        $this->actingAs($authority, 'web')->get('/panel/alertas')->assertForbidden();
    }

    public function test_dashboard_and_pages_render_with_data(): void
    {
        $report = $this->report();
        $this->station(['name' => 'Bomberos Norte', 'installation_type' => 'bombero']);
        $this->actingAs($this->admin, 'web');

        $this->get('/panel')->assertOk()->assertSee('Alertas activas')->assertSee('Ana Admin')->assertSee('Centro');
        $this->get('/panel/mapa')->assertOk()->assertSee('live-map', false);
        $this->get('/panel/alertas')->assertOk()->assertSee('Robo en la avenida');
        $this->get('/panel/alertas?estado=resuelto')->assertOk()->assertDontSee('Robo en la avenida');
        $this->get("/panel/alertas/{$report->id}")->assertOk()->assertSee('Despachar unidad');
        $this->get('/panel/alertas/crear')->assertOk()->assertSee('data-location-picker', false);
        $this->get('/panel/usuarios')->assertOk()->assertSee('Ana Admin');
        $this->get('/panel/usuarios/crear')->assertOk()->assertSee('Perfil de autoridad');
        $this->get('/panel/estaciones')->assertOk()->assertSee('Bomberos Norte');
        $this->get('/panel/estaciones/crear')->assertOk();
        $this->get('/panel/alertas/999999')->assertNotFound();
    }

    public function test_map_data_returns_active_reports_and_stations(): void
    {
        $active = $this->report();
        $this->report(['status' => 'resuelto']);
        $this->station();

        $this->actingAs($this->admin, 'web')->getJson('/panel/mapa/datos')
            ->assertOk()
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('reports.0.id', $active->id)
            ->assertJsonPath('reports.0.coordinates', [-63.18, -17.78])
            ->assertJsonCount(1, 'stations')
            ->assertJsonPath('trackings', []);
    }

    public function test_admin_creates_report_from_panel(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/panel/alertas', ['type' => 2, 'description' => 'Choque', 'latitude' => '-17.7', 'longitude' => '-63.1'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $report = Report::latest('id')->first();
        $this->assertSame('Choque', $report->description);
        $this->assertSame($this->admin->id, $report->creator_id);

        $this->post('/panel/alertas', ['type' => 2, 'description' => ''])->assertSessionHasErrors(['description', 'latitude']);

        $this->post('/panel/alertas', [
            'type' => 3, 'description' => 'Fuego', 'latitude' => '-17.7', 'longitude' => '-63.1', 'zone' => 'Norte',
            'image' => UploadedFile::fake()->image('f.jpg'),
        ])->assertRedirect();
        $this->assertSame(1, Report::latest('id')->first()->images()->count());
    }

    public function test_report_actions_verify_resolve_remove_reactivate_and_dispatch(): void
    {
        $report = $this->report();
        $station = $this->station();
        $authority = User::factory()->role(2)->create();
        $this->actingAs($this->admin, 'web');

        $this->patch("/panel/alertas/{$report->id}/verificar")->assertSessionHas('success');
        $this->assertTrue($report->fresh()->verified);

        $this->post("/panel/alertas/{$report->id}/despachos", ['station_id' => $station->id, 'authority_id' => $authority->id])
            ->assertSessionHas('success');
        $dispatch = Dispatch::first();
        $this->assertSame('en_curso', $dispatch->state->value);

        $this->patch("/panel/alertas/despachos/{$dispatch->id}", ['state' => 'completado'])->assertSessionHas('success');
        $this->assertSame('completado', $dispatch->fresh()->state->value);

        $this->patch("/panel/alertas/{$report->id}/resolver")->assertSessionHas('success');
        $this->assertSame('resuelto', Report::withTrashed()->find($report->id)->status->value);
        // ya cerrada: el servicio responde 404 y el panel lo muestra como mensaje
        $this->patch("/panel/alertas/{$report->id}/resolver")->assertSessionHas('error');

        $this->patch("/panel/alertas/{$report->id}/reactivar")->assertSessionHas('success');
        $this->assertSame('activo', $report->fresh()->status->value);

        $this->delete("/panel/alertas/{$report->id}")->assertSessionHas('success');
        $this->assertSame('eliminado', Report::withTrashed()->find($report->id)->status->value);
        $this->get("/panel/alertas/{$report->id}")->assertOk()->assertSee('Reactivar');
    }

    public function test_users_module_creates_edits_mails_and_deletes(): void
    {
        Mail::fake();
        $this->actingAs($this->admin, 'web');

        $this->post('/panel/usuarios', [
            'role' => 'autoridad', 'first_name' => 'Luis', 'last_name' => 'Paz', 'phone' => '71234567',
            'password' => 'clave123', 'ci' => '1234567', 'gmail' => 'luis@example.com', 'profile_type' => 'policia',
        ])->assertRedirect(route('panel.usuarios.index'))->assertSessionHas('success');
        Mail::assertSent(AuthorityCredentialsMail::class);
        $luis = User::where('phone', '71234567')->firstOrFail();
        $this->assertSame('autoridad', $luis->role->name);
        $this->assertSame('luis@example.com', $luis->authorityProfile->gmail);

        // autoridad sin perfil => error de validación
        $this->post('/panel/usuarios', ['role' => 'autoridad', 'first_name' => 'X', 'last_name' => 'Y', 'phone' => '79999999', 'password' => 'clave123'])
            ->assertSessionHasErrors(['ci', 'gmail', 'profile_type']);
        // teléfono repetido
        $this->post('/panel/usuarios', ['role' => 'usuario', 'first_name' => 'X', 'last_name' => 'Y', 'phone' => '71234567', 'password' => 'clave123'])
            ->assertSessionHasErrors('phone');
        // ciudadano sin perfil
        $this->post('/panel/usuarios', ['role' => 'usuario', 'first_name' => 'Rosa', 'last_name' => 'Gil', 'phone' => '78888888', 'password' => 'clave123'])
            ->assertSessionHas('success');

        $this->get("/panel/usuarios/{$luis->id}/editar")->assertOk()->assertSee('1234567');
        $this->patch("/panel/usuarios/{$luis->id}", [
            'first_name' => 'Luis', 'last_name' => 'Paz Rojas', 'phone' => '71234567',
            'ci' => '1234567', 'gmail' => 'nuevo@example.com', 'profile_type' => 'bombero',
        ])->assertSessionHas('success');
        $this->assertSame('Paz Rojas', $luis->fresh()->last_name);
        $this->assertSame('bombero', $luis->fresh()->authorityProfile->profile_type->value);

        $this->post("/panel/usuarios/{$luis->id}/correo", ['subject' => 'Hola', 'content' => 'Turno de noche'])->assertSessionHas('success');
        $this->post("/panel/usuarios/{$luis->id}/credenciales")->assertSessionHas('success');

        $this->delete("/panel/usuarios/{$this->admin->id}")->assertSessionHas('error');
        $this->delete("/panel/usuarios/{$luis->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($luis);
    }

    public function test_stations_module_crud(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->post('/panel/estaciones', ['name' => 'Hospital Japonés', 'installation_type' => 'hospital', 'latitude' => '-17.77', 'longitude' => '-63.17'])
            ->assertRedirect(route('panel.estaciones.index'));
        $station = EmergencyStation::firstOrFail();

        $this->post('/panel/estaciones', ['name' => '', 'installation_type' => 'cuartel'])->assertSessionHasErrors(['name', 'installation_type', 'latitude']);

        $this->patch("/panel/estaciones/{$station->id}", ['name' => 'Hospital Japonés Central', 'installation_type' => 'hospital', 'latitude' => '-17.76', 'longitude' => '-63.16'])
            ->assertSessionHas('success');
        $this->assertSame(-17.76, $station->fresh()->latitude);

        // las estaciones se eliminan con soft delete: los despachos antiguos conservan su referencia
        Dispatch::create(['report_id' => $this->report()->id, 'destination_id' => $station->id, 'attended_by' => $this->admin->id, 'state' => 'en_curso', 'recorded_at' => now()]);
        $this->delete("/panel/estaciones/{$station->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($station);
        $this->get('/panel/estaciones')->assertOk()->assertDontSee('Hospital Japonés Central');

        $free = $this->station(['name' => 'Libre']);
        $this->delete("/panel/estaciones/{$free->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($free);
    }
}
