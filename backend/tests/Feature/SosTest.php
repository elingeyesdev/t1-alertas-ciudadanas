<?php

namespace Tests\Feature;

use App\Models\AuthorityProfile;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\User;
use App\Services\FcmService;
use Tests\Support\FakeFcm;

/** HU-24 (#12): botón de pánico con asignación automática de la autoridad más cercana. */
class SosTest extends ApiTestCase
{
    private FakeFcm $fcm;

    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fcm = new FakeFcm;
        $this->app->instance(FcmService::class, $this->fcm);
        config(['alertas.ambulance.queue_connection' => 'sync']);
        $this->citizen = $this->makeUser(1, ['first_name' => 'Lucía', 'last_name' => 'Paz', 'phone' => '69100001', 'fcm_token' => 'token-lucia']);

        foreach ([
            ['UTOP Equipetrol', 'policia', -17.7565, -63.1968],
            ['Comando Policía', 'policia', -17.7833, -63.1821],
            ['Hospital Japonés', 'hospital', -17.7598, -63.1902],
        ] as [$name, $type, $lat, $lng]) {
            EmergencyStation::create(['name' => $name, 'installation_type' => $type, 'latitude' => $lat, 'longitude' => $lng]);
        }
    }

    private function unit(string $profile, ?float $lat, ?float $lng, string $token = 'token-unidad'): User
    {
        $unit = $this->makeUser(2, ['last_latitude' => $lat, 'last_longitude' => $lng, 'fcm_token' => $token]);
        AuthorityProfile::create(['user_id' => $unit->id, 'ci' => (string) random_int(100000, 999999), 'gmail' => uniqid().'@example.test', 'profile_type' => $profile]);

        return $unit;
    }

    private function sos(array $extra = [])
    {
        return $this->asUser($this->citizen)->postJson('/api/sos', ['latitude' => -17.7840, 'longitude' => -63.1820] + $extra);
    }

    public function test_sos_creates_priority_alert_and_dispatches_nearest_free_unit_without_verification(): void
    {
        $far = $this->unit('policia', -17.70, -63.10);
        $near = $this->unit('policia', -17.7845, -63.1825, 'token-cerca');
        $busy = $this->unit('policia', -17.7841, -63.1821);
        Dispatch::create([
            'report_id' => Report::create([
                'description' => 'Otro caso', 'latitude' => -17.78, 'longitude' => -63.18, 'weight' => 3, 'zone' => 'Centro',
                'expires_at' => now()->addHour(), 'status' => 'activo', 'creator_id' => $this->makeUser()->id, 'report_type_id' => 1,
            ])->id,
            'destination_id' => EmergencyStation::first()->id, 'attended_by' => $busy->id, 'state' => 'en_curso', 'recorded_at' => now(),
        ]);

        $response = $this->sos(['zone' => 'Centro'])->assertCreated();

        $report = Report::find($response->json('report.id'));
        $this->assertTrue($report->is_sos);
        $this->assertFalse($report->verified);
        $this->assertSame('Botón de pánico (SOS)', $report->type->name);
        $this->assertSame(10, $report->weight);
        $this->assertTrue($response->json('report.is_sos'));

        $dispatch = Dispatch::where('report_id', $report->id)->sole();
        $this->assertSame($near->id, $dispatch->attended_by);
        $this->assertSame('Comando Policía', $dispatch->destination->name);
        $this->assertSame('Comando Policía', $response->json('dispatch.station'));

        // la unidad recibe el push para abrir el despacho
        $this->assertCount(1, $this->fcm->ofType('ambulance_assignment'));
        $this->assertSame(['token-cerca'], $this->fcm->ofType('ambulance_assignment')[0]['tokens']);

        // el ciudadano ve «unidad asignada» y luego «en camino» (HU-26)
        $case = $this->asUser($this->citizen)->getJson("/api/reports/{$report->id}/case")->assertOk();
        $this->assertSame('despachado', $case->json('stage'));
        $this->asUser($near)->postJson('/api/trackings/start', [
            'userId' => $near->id, 'data' => ['status' => 'active', 'reportId' => $report->id],
        ])->assertCreated();
        $this->assertSame('en_camino', $this->asUser($this->citizen)->getJson("/api/reports/{$report->id}/case")->json('stage'));
    }

    public function test_medical_sos_prefers_a_paramedic_and_a_hospital(): void
    {
        $this->unit('policia', -17.7841, -63.1821);
        $medic = $this->unit('paramedico', -17.76, -63.19);

        $response = $this->sos(['kind' => 'medica'])->assertCreated();

        $this->assertSame('paramedico', $response->json('dispatch.profile_type'));
        $this->assertSame('Hospital Japonés', $response->json('dispatch.station'));
        $this->assertSame($medic->id, Dispatch::sole()->attended_by);
    }

    public function test_sos_alerts_the_trust_circle(): void
    {
        $this->unit('policia', -17.78, -63.18);
        $friend = $this->makeUser(1, ['fcm_token' => 'token-amiga']);
        $code = $this->asUser($this->citizen)->postJson('/api/trust-circle/invites')->json('code');
        $this->asUser($friend)->postJson('/api/trust-circle/join', ['code' => $code])->assertCreated();
        $this->asUser($this->citizen)->postJson('/api/trust-circle/contacts', ['name' => 'Mamá', 'phone' => '70000009'])->assertCreated();

        $response = $this->sos()->assertCreated();

        $this->assertSame(1, $response->json('circle.pushed'));
        $push = $this->fcm->ofType('trust_circle_sos')[0];
        $this->assertSame(['token-amiga'], $push['tokens']);
        $this->assertSame((string) $response->json('report.id'), $push['data']['reportId']);
        $this->assertSame('Mamá', $response->json('circle.whatsapp.0.name'));
        $this->assertStringStartsWith('https://wa.me/59170000009', $response->json('circle.whatsapp.0.url'));
    }

    public function test_pressing_again_reuses_the_open_sos_instead_of_duplicating_it(): void
    {
        $this->unit('policia', -17.78, -63.18);
        $first = $this->sos()->assertCreated()->json('report.id');
        $second = $this->sos()->assertOk();

        $this->assertSame($first, $second->json('report.id'));
        $this->assertTrue($second->json('existing'));
        $this->assertSame(1, Report::where('is_sos', true)->count());
        $this->assertSame(1, Dispatch::count());
    }

    public function test_without_free_units_the_sos_is_still_created_for_manual_dispatch(): void
    {
        $response = $this->sos()->assertCreated();

        $this->assertNull($response->json('dispatch'));
        $this->assertTrue(Report::find($response->json('report.id'))->is_sos);
    }

    public function test_only_citizens_can_send_sos_and_location_is_required(): void
    {
        $this->asUser($this->makeUser(2))->postJson('/api/sos', ['latitude' => -17.78, 'longitude' => -63.18])->assertForbidden();
        $this->asUser($this->citizen)->postJson('/api/sos', [])->assertStatus(400);
        $this->asUser($this->citizen)->postJson('/api/sos', ['latitude' => -17.78, 'longitude' => -63.18, 'kind' => 'otra'])->assertStatus(400);
    }
}
