<?php

namespace Tests\Feature;

use App\Mail\AuthorityCredentialsMail;
use App\Mail\UserMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class UsersTest extends ApiTestCase
{
    public function test_admin_creates_authority_and_mail_is_sent(): void
    {
        Mail::fake();
        $admin = $this->makeUser(3);

        $payload = [
            'first_name' => 'Ana', 'last_name' => 'Rojas', 'phone' => '76543210', 'password' => 'clave123',
            'roleId' => 2, 'ci' => ' 1234567 ', 'gmail' => 'Ana@Gmail.com', 'profile_type' => 'policia',
        ];

        $this->asUser($this->makeUser(1))->postJson('/api/users/authority', $payload)->assertStatus(403);

        $res = $this->asUser($admin)->postJson('/api/users/authority', $payload)
            ->assertCreated()
            ->assertJsonPath('authority_profile.gmail', 'ana@gmail.com')
            ->assertJsonPath('authority_profile.ci', '1234567')
            ->assertJsonPath('role.name', 'autoridad');

        Mail::assertSent(AuthorityCredentialsMail::class, fn ($m) => $m->hasTo('ana@gmail.com'));

        $id = $res->json('id');
        $this->asUser($admin)->patchJson("/api/authority-profile/{$id}", ['profile_type' => 'bombero'])
            ->assertOk()->assertJsonPath('profile_type', 'bombero');
        $this->asUser($admin)->patchJson("/api/authority-profile/{$id}", ['profile_type' => 'xx'])
            ->assertStatus(400);

        $this->asUser($admin)->postJson("/api/mail/{$id}", ['subject' => 'Hola', 'content' => 'Mensaje'])
            ->assertCreated();
        Mail::assertSent(UserMessageMail::class);

        $this->asUser($admin)->postJson("/api/mail/{$id}/resend-credentials")->assertCreated();
        $this->asUser($admin)->postJson('/api/mail/'.$admin->id, ['subject' => 'a', 'content' => 'b'])
            ->assertStatus(400);
    }

    public function test_user_crud_endpoints(): void
    {
        $admin = $this->makeUser(3);
        $user = $this->makeUser(1, ['password' => 'vieja123']);

        $this->asUser($user)->getJson('/api/users')->assertOk()->assertJsonCount(2);
        $this->asUser($user)->getJson("/api/users/{$user->id}")->assertOk()->assertJsonPath('id', $user->id);
        $this->asUser($user)->getJson('/api/users/00000000-0000-0000-0000-000000000000')->assertStatus(404);
        $this->asUser($user)->getJson('/api/users/no-es-uuid')->assertStatus(404);

        $this->asUser($user)->patchJson("/api/users/{$user->id}", ['first_name' => ' NUEVO '])
            ->assertOk()->assertJsonPath('first_name', 'nuevo');

        $this->asUser($user)->patchJson("/api/users/{$user->id}/fcm-token", ['fcm_token' => 'tok-1'])->assertOk();
        $this->asUser($admin)->patchJson("/api/users/{$admin->id}/fcm-token", ['fcm_token' => 'tok-1'])->assertOk();
        $this->assertNull($user->fresh()->fcm_token); // el token se movió al admin

        $this->asUser($user)->patchJson("/api/users/{$user->id}/location", ['latitude' => -17.78, 'longitude' => -63.18])
            ->assertOk();
        $this->assertEqualsWithDelta(-17.78, $user->fresh()->last_latitude, 0.0001);

        $this->asUser($user)->patchJson("/api/users/{$user->id}/password", ['current_password' => 'mala', 'new_password' => 'nueva123'])
            ->assertStatus(400);
        $this->asUser($user)->patchJson("/api/users/{$user->id}/password", ['current_password' => 'vieja123', 'new_password' => 'nueva123'])
            ->assertOk();
        $this->postJson('/api/auth/login', ['phone' => $user->phone, 'password' => 'nueva123'])->assertCreated();

        $this->asUser($user)->deleteJson("/api/users/{$user->id}")->assertStatus(403);
        $this->asUser($admin)->deleteJson("/api/users/{$user->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_roles_and_report_types(): void
    {
        $admin = $this->makeUser(3);

        $this->asUser($admin)->getJson('/api/roles')->assertOk()->assertJsonCount(3);
        $id = $this->asUser($admin)->postJson('/api/roles', ['name' => 'supervisor'])->assertCreated()->json('id');
        $this->assertSame(4, $id); // la secuencia quedó sincronizada tras el seeder
        $this->asUser($admin)->deleteJson("/api/roles/{$id}")->assertOk();
        $this->asUser($admin)->deleteJson("/api/roles/{$id}")->assertStatus(404);
        // no se puede borrar un rol en uso
        $this->asUser($admin)->deleteJson('/api/roles/3')->assertStatus(409);

        $this->asUser($admin)->getJson('/api/report-types')->assertOk()->assertJsonCount(7)
            ->assertJsonPath('0.base_weight', 9);
        $tid = $this->asUser($admin)->postJson('/api/report-types', ['name' => 'Inundación'])->assertCreated()
            ->assertJsonPath('base_weight', 1)->json('id');
        $this->asUser($admin)->deleteJson("/api/report-types/{$tid}")->assertOk();
    }
}
