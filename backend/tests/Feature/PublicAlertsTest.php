<?php

namespace Tests\Feature;

use App\Models\PublicAlert;
use App\Models\User;
use App\Services\FcmService;
use App\Services\JwtService;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFcm;
use Tests\TestCase;

/** HU-32 Alertas de salud, viaje o desastres naturales (panel y API). */
class PublicAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FakeFcm $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ReportTypeSeeder::class]);
        $this->admin = $this->makeUser(3);
        $this->fcm = new FakeFcm;
        $this->app->instance(FcmService::class, $this->fcm);
    }

    private function makeUser(int $roleId, array $attrs = []): User
    {
        return User::factory()->role($roleId)->create($attrs);
    }

    /** Petición a la API con el token JWT del usuario. */
    private function asUser(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.app(JwtService::class)->issue($user, JwtService::ACCESS));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'desastre',
            'title' => 'Alerta naranja por lluvias',
            'message' => 'Evita cruzar canales y quebradas hasta nuevo aviso.',
            'latitude' => -17.7833,
            'longitude' => -63.1821,
            'radius_m' => 2000,
            'hours' => 24,
        ], $overrides);
    }

    public function test_admin_publishes_an_alert_and_only_citizens_inside_the_zone_get_the_push(): void
    {
        $inside = $this->makeUser(1, ['last_latitude' => -17.7840, 'last_longitude' => -63.1830, 'fcm_token' => 'dentro']);
        $this->makeUser(1, ['last_latitude' => -17.9000, 'last_longitude' => -63.3000, 'fcm_token' => 'fuera']);
        $this->makeUser(1, ['last_latitude' => -17.7835, 'last_longitude' => -63.1825, 'fcm_token' => 'privado', 'location_sharing' => false]);
        $this->makeUser(2, ['last_latitude' => -17.7835, 'last_longitude' => -63.1825, 'fcm_token' => 'autoridad']);

        $this->actingAs($this->admin, 'web')
            ->post('/panel/avisos', $this->payload())
            ->assertRedirect(route('panel.avisos.index'))
            ->assertSessionHas('success', 'Aviso publicado. Se notificó a 1 ciudadano dentro de la zona.');

        $alert = PublicAlert::sole();
        $this->assertSame('desastre', $alert->category->value);
        $this->assertSame($this->admin->id, $alert->created_by);
        $this->assertSame(1, $alert->notified_count);
        $this->assertTrue($alert->expires_at->between(now()->addHours(23), now()->addHours(25)));

        $pushes = $this->fcm->ofType('public_alert');
        $this->assertCount(1, $pushes);
        $this->assertSame([$inside->fcm_token], $pushes[0]['tokens']);
        $this->assertSame('Desastre natural: Alerta naranja por lluvias', $pushes[0]['title']);
        $this->assertSame((string) $alert->id, $pushes[0]['data']['publicAlertId']);
    }

    public function test_title_message_zone_and_validity_are_required(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/panel/avisos', ['category' => 'otro', 'radius_m' => 123, 'hours' => 5])
            ->assertSessionHasErrors(['category', 'title', 'message', 'latitude', 'longitude', 'radius_m', 'hours']);

        $this->assertSame(0, PublicAlert::count());
        $this->assertSame([], $this->fcm->sent);
    }

    public function test_only_the_admin_can_publish(): void
    {
        $authority = $this->makeUser(2);
        $this->actingAs($authority, 'web')->post('/panel/avisos', $this->payload())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post('/panel/avisos', $this->payload())->assertRedirect(route('panel.login'));

        $this->assertSame(0, PublicAlert::count());
    }

    public function test_the_app_only_receives_alerts_that_are_still_valid(): void
    {
        $citizen = $this->makeUser(1);
        $valid = PublicAlert::create($this->payload(['title' => 'Vigente', 'expires_at' => now()->addHours(3)]));
        PublicAlert::create($this->payload(['title' => 'Vencido', 'expires_at' => now()->subMinute()]));

        $this->asUser($citizen)->getJson('/api/public-alerts')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $valid->id)
            ->assertJsonPath('0.category_label', 'Desastre natural')
            ->assertJsonPath('0.coordinates', [-63.1821, -17.7833])
            ->assertJsonPath('0.radius_m', 2000);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/public-alerts')->assertUnauthorized();
    }

    public function test_finishing_an_alert_removes_it_from_the_maps(): void
    {
        $alert = PublicAlert::create($this->payload(['expires_at' => now()->addDay()]));

        $this->actingAs($this->admin, 'web')->getJson('/panel/mapa/datos')
            ->assertOk()->assertJsonCount(1, 'public_alerts');

        $this->actingAs($this->admin, 'web')
            ->patch("/panel/avisos/{$alert->id}/finalizar")
            ->assertSessionHas('success');

        $this->assertFalse($alert->fresh()->isActive());
        $this->actingAs($this->admin, 'web')->getJson('/panel/mapa/datos')->assertJsonCount(0, 'public_alerts');

        // Finalizar de nuevo no hace nada y avisa
        $this->actingAs($this->admin, 'web')
            ->patch("/panel/avisos/{$alert->id}/finalizar")
            ->assertSessionHas('error', 'El aviso ya no está vigente.');
    }

    public function test_panel_pages_render(): void
    {
        PublicAlert::create($this->payload(['expires_at' => now()->addDay()]));
        PublicAlert::create($this->payload(['title' => 'Ola de calor', 'category' => 'salud', 'expires_at' => now()->subDay()]));

        $this->actingAs($this->admin, 'web')->get('/panel/avisos')
            ->assertOk()->assertSee('Alerta naranja por lluvias')->assertSee('Ola de calor')->assertSee('Vigente')->assertSee('Finalizado');
        $this->actingAs($this->admin, 'web')->get('/panel/avisos?estado=vigentes')
            ->assertOk()->assertSee('Alerta naranja por lluvias')->assertDontSee('Ola de calor');
        $this->actingAs($this->admin, 'web')->get('/panel/avisos/crear')
            ->assertOk()->assertSee('Publicar aviso')->assertSee('Toda la ciudad (30 km)');
    }

    public function test_admin_can_delete_an_alert(): void
    {
        $alert = PublicAlert::create($this->payload(['expires_at' => now()->addDay()]));

        $this->actingAs($this->admin, 'web')->delete("/panel/avisos/{$alert->id}")->assertSessionHas('success', 'Aviso eliminado.');

        $this->assertSoftDeleted($alert);
        $this->asUser($this->makeUser(1))->getJson('/api/public-alerts')->assertJsonCount(0);
    }
}
