<?php

namespace Tests\Feature;

use App\Models\AuthorityProfile;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\User;
use App\Services\FcmService;
use Tests\Support\FakeFcm;

/** HU-26 (#14): el ciudadano sigue el estado de su reporte. */
class CaseTrackingTest extends ApiTestCase
{
    private FakeFcm $fcm;

    private User $citizen;

    private User $unit;

    private EmergencyStation $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fcm = new FakeFcm;
        $this->app->instance(FcmService::class, $this->fcm);

        $this->citizen = $this->makeUser(1, ['fcm_token' => 'token-ciudadano']);
        $this->unit = $this->makeUser(2, ['first_name' => 'Ana', 'last_name' => 'Rojas']);
        AuthorityProfile::create([
            'user_id' => $this->unit->id, 'ci' => '123456', 'gmail' => 'ana@example.test', 'profile_type' => 'paramedico',
        ]);
        $this->station = EmergencyStation::create([
            'name' => 'Hospital Japonés', 'installation_type' => 'hospital', 'latitude' => -17.77, 'longitude' => -63.17,
        ]);
    }

    private function createReport(): int
    {
        return $this->asUser($this->citizen)->postJson('/api/reports', [
            'type' => 4, 'description' => 'Persona inconsciente', 'latitude' => -17.78, 'longitude' => -63.18, 'zone' => 'Centro',
        ])->assertCreated()->json('id');
    }

    public function test_full_case_flow_builds_timeline_and_pushes_each_step_to_the_creator(): void
    {
        $reportId = $this->createReport();

        $this->asUser($this->unit)->patchJson("/api/reports/{$reportId}/verify")->assertOk();
        $dispatchId = $this->asUser($this->unit)->postJson('/api/dispatches', [
            'reportId' => $reportId, 'destinationId' => $this->station->id,
        ])->assertCreated()->json('id');

        $case = $this->asUser($this->citizen)->getJson("/api/reports/{$reportId}/case")->assertOk();
        $this->assertSame('despachado', $case->json('stage'));
        $this->assertSame('Ana Rojas', $case->json('dispatches.0.unit.name'));
        $this->assertSame('Hospital Japonés', $case->json('dispatches.0.station.name'));

        // La unidad arranca la navegación: el caso pasa a «en camino» (una sola vez)
        foreach ([1, 2] as $_) {
            $this->asUser($this->unit)->postJson('/api/trackings/start', [
                'userId' => $this->unit->id, 'data' => ['status' => 'active', 'reportId' => $reportId, 'latitude' => -17.77, 'longitude' => -63.17],
            ])->assertCreated();
        }
        $this->assertNotNull(Dispatch::find($dispatchId)->departed_at);

        $this->asUser($this->unit)->patchJson("/api/dispatches/{$dispatchId}/state", ['state' => 'completado'])->assertOk();

        $case = $this->asUser($this->citizen)->getJson("/api/reports/{$reportId}/case")->assertOk();
        $this->assertSame('completado', $case->json('stage'));
        $this->assertSame(
            ['creado', 'verificado', 'despachado', 'en_camino', 'completado'],
            array_column($case->json('timeline'), 'type'),
        );

        $pushes = $this->fcm->ofType('case_update');
        $this->assertSame(['verificado', 'despachado', 'en_camino', 'completado'], array_map(fn ($p) => $p['data']['event'], $pushes));
        foreach ($pushes as $push) {
            $this->assertSame(['token-ciudadano'], $push['tokens']);
            $this->assertSame((string) $reportId, $push['data']['reportId']);
        }
        $this->assertSame('Unidad en camino', $pushes[2]['title']);
    }

    public function test_only_the_creator_or_an_admin_sees_the_case_detail(): void
    {
        $reportId = $this->createReport();

        $this->asUser($this->makeUser(1))->getJson("/api/reports/{$reportId}/case")->assertStatus(403);
        $this->asUser($this->unit)->getJson("/api/reports/{$reportId}/case")->assertStatus(403);
        $this->asUser($this->makeUser(3))->getJson("/api/reports/{$reportId}/case")->assertOk();
        $this->asUser($this->citizen)->getJson('/api/reports/999999/case')->assertNotFound();
    }

    public function test_only_the_assigned_unit_can_mark_the_case_en_route(): void
    {
        $reportId = $this->createReport();
        $dispatchId = $this->asUser($this->unit)->postJson('/api/dispatches', [
            'reportId' => $reportId, 'destinationId' => $this->station->id,
        ])->assertCreated()->json('id');

        $intruder = $this->makeUser(2);
        $this->asUser($intruder)->postJson('/api/trackings/start', [
            'userId' => $intruder->id, 'data' => ['status' => 'active', 'reportId' => $reportId],
        ])->assertCreated();
        // un trazado de ruta («planned») tampoco cuenta como salida
        $this->asUser($this->unit)->postJson('/api/trackings/start', [
            'userId' => $this->unit->id, 'data' => ['status' => 'planned', 'reportId' => $reportId],
        ])->assertCreated();

        $this->assertNull(Dispatch::find($dispatchId)->departed_at);
        $this->assertSame('despachado', $this->asUser($this->citizen)->getJson("/api/reports/{$reportId}/case")->json('stage'));
    }

    public function test_cancelled_dispatch_and_resolution_are_recorded(): void
    {
        $reportId = $this->createReport();
        $dispatchId = $this->asUser($this->unit)->postJson('/api/dispatches', [
            'reportId' => $reportId, 'destinationId' => $this->station->id,
        ])->assertCreated()->json('id');
        $this->asUser($this->unit)->patchJson("/api/dispatches/{$dispatchId}/state", ['state' => 'cancelado'])->assertOk();
        $this->asUser($this->citizen)->patchJson("/api/reports/{$reportId}/resolve")->assertOk();

        $case = $this->asUser($this->citizen)->getJson("/api/reports/{$reportId}/case")->assertOk();
        $this->assertSame('resuelto', $case->json('stage'));
        $this->assertSame(['creado', 'despachado', 'cancelado', 'resuelto'], array_column($case->json('timeline'), 'type'));
    }

    public function test_reports_created_before_the_feature_still_show_a_timeline(): void
    {
        $report = Report::create([
            'description' => 'Antiguo', 'latitude' => -17.78, 'longitude' => -63.18, 'weight' => 3, 'zone' => 'Centro',
            'expires_at' => now()->addHours(2), 'status' => 'activo', 'creator_id' => $this->citizen->id, 'report_type_id' => 1,
        ]);
        Dispatch::create([
            'report_id' => $report->id, 'destination_id' => $this->station->id, 'attended_by' => $this->unit->id,
            'state' => 'en_curso', 'recorded_at' => now(),
        ]);

        $case = $this->asUser($this->citizen)->getJson("/api/reports/{$report->id}/case")->assertOk();
        $this->assertSame('despachado', $case->json('stage'));
        $this->assertSame(['creado', 'despachado'], array_column($case->json('timeline'), 'type'));
    }
}
