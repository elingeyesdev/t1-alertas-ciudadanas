<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\User;

/** Issue #9: cada acción solo la puede hacer quien corresponde (403 si no). */
class PermissionsTest extends ApiTestCase
{
    private User $citizen;

    private User $other;

    private User $authority;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citizen = $this->makeUser(1, ['password' => 'secreto1']);
        $this->other = $this->makeUser(1);
        $this->authority = $this->makeUser(2);
        $this->admin = $this->makeUser(3);
    }

    private function report(User $creator): Report
    {
        return Report::create([
            'description' => 'Robo', 'latitude' => -17.78, 'longitude' => -63.18, 'weight' => 3, 'zone' => 'Centro',
            'expires_at' => now()->addHours(2), 'status' => 'activo', 'creator_id' => $creator->id, 'report_type_id' => 1,
        ]);
    }

    public function test_users_can_only_change_their_own_account(): void
    {
        $id = $this->other->id;
        $this->asUser($this->citizen)->getJson("/api/users/{$id}")->assertStatus(403);
        $this->asUser($this->citizen)->patchJson("/api/users/{$id}", ['first_name' => 'hack'])->assertStatus(403);
        $this->asUser($this->citizen)->patchJson("/api/users/{$id}/password", ['current_password' => 'x', 'new_password' => 'nueva123'])->assertStatus(403);
        $this->asUser($this->citizen)->patchJson("/api/users/{$id}/location", ['latitude' => 1, 'longitude' => 1])->assertStatus(403);
        $this->asUser($this->citizen)->patchJson("/api/users/{$id}/fcm-token", ['fcm_token' => 'robado'])->assertStatus(403);
        $this->assertNotSame('hack', $this->other->fresh()->first_name);

        // su propia cuenta sí
        $this->asUser($this->citizen)->patchJson("/api/users/{$this->citizen->id}", ['first_name' => 'Luis'])->assertOk();
        // el admin puede ver y editar datos de otros, pero no su contraseña
        $this->asUser($this->admin)->getJson("/api/users/{$id}")->assertOk();
        $this->asUser($this->admin)->patchJson("/api/users/{$id}", ['first_name' => 'Corregido'])->assertOk();
        $this->asUser($this->admin)->patchJson("/api/users/{$id}/password", ['current_password' => 'x', 'new_password' => 'nueva123'])->assertStatus(403);
    }

    public function test_user_list_and_full_history_are_admin_only(): void
    {
        foreach ([$this->citizen, $this->authority] as $user) {
            $this->asUser($user)->getJson('/api/users')->assertStatus(403);
            $this->asUser($user)->getJson('/api/reports/with-deleted')->assertStatus(403);
        }
        $this->asUser($this->admin)->getJson('/api/users')->assertOk();
        $this->asUser($this->admin)->getJson('/api/reports/with-deleted')->assertOk();
    }

    public function test_only_creator_or_staff_can_resolve_reactivate_or_delete_a_report(): void
    {
        $report = $this->report($this->citizen);

        $this->asUser($this->other)->patchJson("/api/reports/{$report->id}/resolve")->assertStatus(403);
        $this->asUser($this->other)->deleteJson("/api/reports/{$report->id}")->assertStatus(403);
        $this->assertSame('activo', $report->fresh()->status->value);

        $this->asUser($this->citizen)->patchJson("/api/reports/{$report->id}/resolve")->assertOk();
        $this->asUser($this->other)->patchJson("/api/reports/{$report->id}/reactivate")->assertStatus(403);
        $this->asUser($this->authority)->patchJson("/api/reports/{$report->id}/reactivate")->assertOk();
        $this->asUser($this->admin)->deleteJson("/api/reports/{$report->id}")->assertOk();

        $this->asUser($this->other)->deleteJson('/api/reports/999999')->assertStatus(404);
    }

    public function test_stations_mail_and_authority_profiles_are_admin_only(): void
    {
        $station = EmergencyStation::create(['name' => 'EPI', 'installation_type' => 'policia', 'latitude' => -17.7, 'longitude' => -63.1]);
        $payload = ['name' => 'Nueva', 'installation_type' => 'bombero', 'latitude' => -17.7, 'longitude' => -63.1];

        foreach ([$this->citizen, $this->authority] as $user) {
            $this->asUser($user)->getJson('/api/emergency-stations')->assertOk(); // ver el mapa sí
            $this->asUser($user)->postJson('/api/emergency-stations', $payload)->assertStatus(403);
            $this->asUser($user)->patchJson("/api/emergency-stations/{$station->id}", ['name' => 'x'])->assertStatus(403);
            $this->asUser($user)->deleteJson("/api/emergency-stations/{$station->id}")->assertStatus(403);
            $this->asUser($user)->postJson("/api/mail/{$this->authority->id}", ['subject' => 'a', 'content' => 'b'])->assertStatus(403);
            $this->asUser($user)->postJson("/api/mail/{$this->authority->id}/resend-credentials")->assertStatus(403);
            $this->asUser($user)->patchJson("/api/authority-profile/{$this->authority->id}", ['ci' => '1'])->assertStatus(403);
        }
        $this->asUser($this->admin)->postJson('/api/emergency-stations', $payload)->assertCreated();
    }

    public function test_dispatches_are_for_authorities_and_admins(): void
    {
        $report = $this->report($this->citizen);
        $station = EmergencyStation::create(['name' => 'EPI', 'installation_type' => 'policia', 'latitude' => -17.7, 'longitude' => -63.1]);
        $body = ['reportId' => $report->id, 'destinationId' => $station->id];

        $this->asUser($this->citizen)->postJson('/api/dispatches', $body)->assertStatus(403);
        $id = $this->asUser($this->authority)->postJson('/api/dispatches', $body)->assertCreated()->json('id');

        $this->asUser($this->citizen)->patchJson("/api/dispatches/{$id}/state", ['state' => 'cancelado'])->assertStatus(403);
        $this->asUser($this->admin)->patchJson("/api/dispatches/{$id}/state", ['state' => 'completado'])->assertOk();
        $this->assertSame('completado', Dispatch::find($id)->state->value);
    }
}
