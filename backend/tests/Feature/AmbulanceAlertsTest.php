<?php

namespace Tests\Feature;

use App\Jobs\EvaluateAmbulanceRoute;
use App\Models\User;
use App\Services\AmbulanceTracking;
use App\Services\FcmService;
use Database\Seeders\EmergencyStationSeeder;
use Database\Seeders\SampleReportSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AmbulanceAlertsTest extends ApiTestCase
{
    public function test_approaching_route_queues_one_notice_only_for_the_eligible_citizen(): void
    {
        [$dispatch, $unit] = $this->dispatchFixture();
        $eligible = $this->citizenAt(-63.185, -17.77955);
        $this->citizenAt(-63.189, -17.77955); // Behind.
        $this->citizenAt(-63.185, -17.77865); // More than 100 m to the side.
        $this->citizenAt(-63.158, -17.78); // More than five minutes ahead.
        $this->citizenAt(-63.185, -17.77955, age: 90);
        $this->citizenAt(-63.185, -17.77955, consent: false);

        $position = $this->sample(-63.188, -17.78);
        $this->asUser($this->makeUser(2))->postJson("/api/dispatches/{$dispatch}/ambulance-position", $position)
            ->assertForbidden();
        $this->asUser($unit)->postJson("/api/dispatches/{$dispatch}/ambulance-position", $position)
            ->assertNoContent();
        $this->app->call([new EvaluateAmbulanceRoute($dispatch), 'handle']);

        $this->assertDatabaseCount('ambulance_alerts', 1);
        $this->assertDatabaseCount('ambulance_alert_outbox', 1);
        $this->assertDatabaseHas('ambulance_alerts', ['user_id' => $eligible->id, 'route_version' => 1]);
        Http::assertSentCount(1);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('sendAmbulanceAlert')->once()->with('test-token', \Mockery::type('string'), \Mockery::type('int'))
                ->andReturn('sent');
        });
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->assertDatabaseHas('ambulance_alert_outbox', ['status' => 'sent', 'attempts' => 1]);
        $alert = DB::table('ambulance_alerts')->first();
        $this->assertNotNull($alert->fcm_accepted_at);

        $this->asUser($this->makeUser())->postJson("/api/ambulance-alerts/{$alert->id}/receipt", ['event' => 'foreground'])
            ->assertNoContent();
        $this->assertDatabaseHas('ambulance_alerts', ['id' => $alert->id, 'foreground_received_at' => null]);
        $this->asUser($eligible)->postJson("/api/ambulance-alerts/{$alert->id}/receipt", ['event' => 'foreground'])
            ->assertNoContent();
        $this->assertNotNull(DB::table('ambulance_alerts')->where('id', $alert->id)->value('foreground_received_at'));
    }

    public function test_revocation_cancels_pending_delivery(): void
    {
        [$dispatch, $unit] = $this->dispatchFixture();
        $eligible = $this->citizenAt(-63.185, -17.77955);
        $this->asUser($unit)->postJson("/api/dispatches/{$dispatch}/ambulance-position", $this->sample(-63.188, -17.78))
            ->assertNoContent();
        $this->asUser($eligible)->putJson('/api/ambulance-alerts/consent', ['enabled' => false])->assertNoContent();
        $this->postJson('/api/ambulance-alerts/position', $this->sample(-63.185, -17.77955))->assertNoContent();
        $this->assertDatabaseMissing('ambulance_alert_locations', ['user_id' => $eligible->id]);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldNotReceive('sendAmbulanceAlert');
        });
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->assertDatabaseHas('ambulance_alert_outbox', ['status' => 'suppressed']);
    }

    public function test_same_gps_point_queues_and_delivers_an_immediate_alert(): void
    {
        [$dispatch, $unit] = $this->dispatchFixture();
        $citizen = $this->citizenAt(-63.188, -17.78);
        $this->asUser($unit)->postJson("/api/dispatches/{$dispatch}/ambulance-position", $this->sample(-63.188, -17.78))
            ->assertNoContent();
        $this->assertDatabaseHas('ambulance_alerts', ['dispatch_id' => $dispatch, 'user_id' => $citizen->id, 'eta_s' => 0]);
        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('sendAmbulanceAlert')->once()->andReturn('sent');
        });
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->assertDatabaseHas('ambulance_alert_outbox', ['status' => 'sent']);
    }

    public function test_outbox_retries_definite_failure_but_does_not_repeat_an_uncertain_send(): void
    {
        [$dispatch, $unit] = $this->dispatchFixture();
        $this->citizenAt(-63.185, -17.77955);
        $this->asUser($unit)->postJson("/api/dispatches/{$dispatch}/ambulance-position", $this->sample(-63.188, -17.78))
            ->assertNoContent();
        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('sendAmbulanceAlert')->twice()->andReturn('retry', 'uncertain');
        });

        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->assertDatabaseHas('ambulance_alert_outbox', ['status' => 'pending', 'attempts' => 1]);
        DB::table('ambulance_alert_outbox')->update(['available_at' => now()->subSecond()]);
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->assertDatabaseHas('ambulance_alert_outbox', ['status' => 'uncertain', 'attempts' => 2]);
    }

    public function test_reroute_replaces_the_route_and_suppresses_an_obsolete_notice(): void
    {
        [$dispatch, $unit] = $this->dispatchFixture([
            [[-63.19, -17.78], [-63.155, -17.78]],
            [[-63.19, -17.777], [-63.155, -17.777]],
        ]);
        $this->citizenAt(-63.185, -17.77955);
        $this->asUser($unit)->postJson("/api/dispatches/{$dispatch}/ambulance-position", $this->sample(-63.188, -17.78))
            ->assertNoContent();

        $tracking = app(AmbulanceTracking::class);
        $tracking->cache()->forget($tracking->key($dispatch, 'directions_attempt'));
        foreach ([3, 2, 1] as $seconds) {
            $position = $this->sample(-63.188, -17.777);
            $position['captured_at'] = now()->subSeconds($seconds)->toISOString();
            $tracking->cache()->put($tracking->key($dispatch, 'position'), $position, 60);
            $this->app->call([new EvaluateAmbulanceRoute($dispatch), 'handle']);
        }
        $this->assertDatabaseHas('dispatch_routes', ['dispatch_id' => $dispatch, 'route_version' => 1, 'status' => 'replaced']);
        $this->assertDatabaseHas('dispatch_routes', ['dispatch_id' => $dispatch, 'route_version' => 2, 'status' => 'active']);
        $this->assertDatabaseCount('ambulance_alerts', 1);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldNotReceive('sendAmbulanceAlert');
        });
        $this->artisan('ambulance:deliver-alerts')->assertSuccessful();
        $this->assertDatabaseHas('ambulance_alert_outbox', ['status' => 'suppressed']);
    }

    public function test_firebase_payload_only_contains_the_opaque_notice_reference(): void
    {
        $credentials = tempnam(sys_get_temp_dir(), 'ambulance-fcm');
        try {
            file_put_contents($credentials, '{"project_id":"example"}');
            config(['alertas.firebase.credentials' => $credentials]);
            Cache::put('fcm_access_token', 'fake-access-token', 30);
            Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/example/messages/example'])]);

            $id = 'c408ad56-54f7-4fc4-a716-7aa3530caae6';
            $expires = time() + 10;
            $this->assertSame('sent', app(FcmService::class)->sendAmbulanceAlert('fake-device-token', $id, $expires));
            Http::assertSent(function ($request) use ($id, $expires) {
                $payload = $request['message'];

                return $payload['data'] === ['type' => 'ambulance_approaching', 'alert_id' => $id]
                    && $payload['android']['notification']['channel_id'] === 'ambulance_alerts_sound_v3'
                    && $payload['android']['notification']['sound'] === 'ambulance_proximity_siren'
                    && $payload['android']['notification']['tag'] === $id
                    && $payload['apns']['headers']['apns-expiration'] === (string) $expires;
            });
        } finally {
            unlink($credentials);
        }
    }

    public function test_ambiguous_crossing_and_expired_gps_do_not_produce_an_alert(): void
    {
        [$dispatch, $unit] = $this->dispatchFixture([[
            [-63.190, -17.782], [-63.186, -17.778],
            [-63.190, -17.778], [-63.186, -17.782],
        ]]);
        $this->citizenAt(-63.185, -17.77955);
        $this->asUser($unit)->postJson("/api/dispatches/{$dispatch}/ambulance-position", $this->sample(-63.188, -17.78))
            ->assertNoContent();
        $route = DB::table('dispatch_routes')->where('dispatch_id', $dispatch)->first();
        $projection = app(\App\Services\AmbulanceProximity::class)->progress(
            $route->id, $this->sample(-63.188, -17.78),
        );
        $this->assertTrue($projection->ambiguous);
        $this->assertDatabaseCount('ambulance_alerts', 0);

        $stale = $this->sample(-63.188, -17.78);
        $stale['captured_at'] = now()->subSeconds(31)->toISOString();
        $this->postJson("/api/dispatches/{$dispatch}/ambulance-position", $stale)->assertBadRequest();
        $this->patchJson("/api/dispatches/{$dispatch}/state", ['state' => 'cancelado'])->assertOk();
        $this->postJson("/api/dispatches/{$dispatch}/ambulance-position", $this->sample(-63.188, -17.78))
            ->assertConflict();
        $this->assertDatabaseHas('dispatch_routes', ['id' => $route->id, 'status' => 'completed']);
        $this->assertDatabaseCount('ambulance_alerts', 0);
    }

    private function dispatchFixture(array $lines = [[[-63.19, -17.78], [-63.155, -17.78]]]): array
    {
        config([
            'alertas.ambulance.cache_store' => 'array',
            'alertas.ambulance.queue_connection' => 'sync',
            'alertas.ambulance.notifications_enabled' => true,
            'services.mapbox.directions_token' => 'test-token',
        ]);
        $sequence = Http::sequence();
        foreach ($lines as $coordinates) {
            $sequence->push([
                'code' => 'Ok',
                'routes' => [[
                    'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
                    'distance' => 3700, 'duration' => 370,
                ]],
            ]);
        }
        Http::fake(['api.mapbox.com/*' => $sequence]);
        $this->seed([EmergencyStationSeeder::class]);
        $unit = $this->makeUser(2);
        $unit->authorityProfile()->create(['ci' => '999', 'gmail' => 'paramedico@example.test', 'profile_type' => 'paramedico']);
        $this->seed(SampleReportSeeder::class);

        $dispatch = DB::table('dispatches')->insertGetId([
            'attended_by' => $unit->id,
            'report_id' => DB::table('reports')->min('id'),
            'destination_id' => DB::table('emergency_stations')->min('id'),
        ]);

        return [$dispatch, $unit];
    }

    private function citizenAt(float $longitude, float $latitude, int $age = 0, bool $consent = true): User
    {
        $user = $this->makeUser(attrs: ['fcm_token' => 'test-token']);
        $this->asUser($user)->putJson('/api/ambulance-alerts/consent', ['enabled' => $consent])->assertNoContent();
        $this->postJson('/api/ambulance-alerts/position', $this->sample($longitude, $latitude))->assertNoContent();
        if ($age > 0) {
            DB::table('ambulance_alert_locations')->where('user_id', $user->id)
                ->update(['captured_at' => now()->subSeconds($age)]);
        }

        return $user;
    }

    private function sample(float $longitude, float $latitude): array
    {
        return ['longitude' => $longitude, 'latitude' => $latitude, 'accuracy_m' => 8, 'captured_at' => now()->toISOString()];
    }
}
