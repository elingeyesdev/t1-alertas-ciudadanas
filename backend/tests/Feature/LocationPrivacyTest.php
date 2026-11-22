<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeFcm;

/** HU-29 (#17): el ciudadano controla si comparte su ubicación. */
class LocationPrivacyTest extends ApiTestCase
{
    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citizen = $this->makeUser(1, ['last_latitude' => -17.78, 'last_longitude' => -63.18, 'fcm_token' => 'token-vecino']);
    }

    public function test_sharing_is_on_by_default_and_explains_what_happens_without_it(): void
    {
        $this->asUser($this->citizen)->getJson('/api/auth/location-sharing')->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('has_saved_location', true)
            ->assertJson(fn ($json) => $json->where('notice', fn ($text) => str_contains($text, 'no te llegan avisos'))->etc());
    }

    public function test_turning_it_off_deletes_the_last_saved_location_and_stops_saving_new_ones(): void
    {
        DB::table('ambulance_alert_locations')->insert(['user_id' => $this->citizen->id]);

        $this->asUser($this->citizen)->putJson('/api/auth/location-sharing', ['enabled' => false])->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('has_saved_location', false);

        $fresh = $this->citizen->fresh();
        $this->assertNull($fresh->last_latitude);
        $this->assertNull($fresh->last_longitude);
        $this->assertDatabaseMissing('ambulance_alert_locations', ['user_id' => $this->citizen->id]);

        // la app puede seguir enviando su GPS, pero ya no se guarda
        $this->asUser($this->citizen)->patchJson("/api/users/{$this->citizen->id}/location", ['latitude' => -17.7, 'longitude' => -63.1])
            ->assertOk()->assertJsonPath('saved', false);
        $this->assertNull($this->citizen->fresh()->last_latitude);

        // y no puede activar los avisos de ambulancia
        $this->asUser($this->citizen)->putJson('/api/ambulance-alerts/consent', ['enabled' => true])->assertStatus(409);
    }

    public function test_turning_it_back_on_saves_locations_again(): void
    {
        $this->asUser($this->citizen)->putJson('/api/auth/location-sharing', ['enabled' => false])->assertOk();
        $this->asUser($this->citizen)->putJson('/api/auth/location-sharing', ['enabled' => true])->assertOk()
            ->assertJsonPath('enabled', true);

        $this->asUser($this->citizen)->patchJson("/api/users/{$this->citizen->id}/location", ['latitude' => -17.7, 'longitude' => -63.1])
            ->assertOk()->assertJsonPath('saved', true);
        $this->assertSame(-17.7, $this->citizen->fresh()->last_latitude);
    }

    public function test_without_location_no_nearby_alerts_arrive(): void
    {
        $fcm = new FakeFcm;
        $this->app->instance(FcmService::class, $fcm);
        $this->asUser($this->citizen)->putJson('/api/auth/location-sharing', ['enabled' => false])->assertOk();

        $report = Report::create([
            'description' => 'Robo', 'latitude' => -17.78, 'longitude' => -63.18, 'weight' => 5, 'zone' => 'Centro',
            'expires_at' => now()->addHour(), 'status' => 'activo', 'creator_id' => $this->makeUser()->id, 'report_type_id' => 2,
        ]);
        app(NotificationService::class)->notifyNearbyUsers($report);

        foreach ($fcm->sent as $push) {
            $this->assertNotContains('token-vecino', $push['tokens']);
        }
    }

    public function test_validation_and_roles(): void
    {
        $this->asUser($this->citizen)->putJson('/api/auth/location-sharing', [])->assertStatus(400);
        $this->asUser($this->makeUser(2))->getJson('/api/auth/location-sharing')->assertForbidden();
        $this->flushHeaders()->getJson('/api/auth/location-sharing')->assertUnauthorized();
    }
}
