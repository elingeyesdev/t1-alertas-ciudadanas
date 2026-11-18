<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Image;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Issue #8: rol fijo en el registro y usuario tomado del token.
 * Nadie puede registrarse como autoridad/admin ni actuar a nombre de otro.
 */
class SecurityIdentityTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function register(array $extra = []): TestResponse
    {
        return $this->postJson('/api/auth/register', array_merge([
            'first_name' => 'Eva', 'last_name' => 'Rios', 'phone' => '71112222', 'password' => 'secreto1',
        ], $extra));
    }

    public function test_register_ignores_any_role_sent_by_the_client(): void
    {
        foreach ([3, 2, '3', 'admin'] as $i => $role) {
            $phone = '7111222'.$i;
            $this->register(['phone' => $phone, 'roleId' => $role, 'role_id' => 3, 'role' => 'admin'])
                ->assertCreated()
                ->assertJsonPath('user.role.name', 'usuario');
            $this->assertSame('usuario', User::where('phone', $phone)->first()->role->name);
        }

        // sin roleId también funciona (el campo ya no es obligatorio)
        $this->register(['phone' => '71119999'])->assertCreated()->assertJsonPath('user.role.name', 'usuario');
    }

    public function test_a_self_registered_user_cannot_use_admin_endpoints(): void
    {
        $token = $this->register(['roleId' => 3])->json('access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/users/authority', [
                'first_name' => 'x', 'last_name' => 'y', 'phone' => '72223333', 'password' => 'secreto1',
                'roleId' => 3, 'ci' => '1', 'gmail' => 'a@b.com', 'profile_type' => 'policia',
            ])->assertStatus(403);
    }

    public function test_authority_endpoint_always_creates_authorities(): void
    {
        $admin = $this->makeUser(3);
        $this->asUser($admin)->postJson('/api/users/authority', [
            'first_name' => 'Ana', 'last_name' => 'Paz', 'phone' => '72224444', 'password' => 'secreto1',
            'roleId' => 3, 'ci' => '123', 'gmail' => 'ana@example.com', 'profile_type' => 'policia',
        ])->assertCreated();

        $this->assertSame('autoridad', User::where('phone', '72224444')->first()->role->name);
    }

    public function test_login_answers_the_same_for_unknown_phone_and_wrong_password(): void
    {
        $this->makeUser(1, ['phone' => '73334444', 'password' => 'secreto1']);

        $unknown = $this->postJson('/api/auth/login', ['phone' => '79990000', 'password' => 'secreto1']);
        $wrong = $this->postJson('/api/auth/login', ['phone' => '73334444', 'password' => 'otra123']);

        $unknown->assertStatus(400);
        $wrong->assertStatus(400);
        $this->assertSame($unknown->json(), $wrong->json());
    }

    public function test_report_comment_image_and_dispatch_use_the_token_user(): void
    {
        $me = $this->makeUser(1);
        $victim = $this->makeUser(1);

        $report = $this->asUser($me)->post('/api/reports', [
            'type' => 3, 'description' => 'Humo en la esquina', 'latitude' => '-17.78', 'longitude' => '-63.18',
            'userId' => $victim->id,
        ], ['Accept' => 'application/json'])->assertCreated()->json();
        $this->assertSame($me->id, Report::find($report['id'])->creator_id);

        $comment = $this->asUser($me)->postJson("/api/reports/{$report['id']}/comments", ['creatorId' => $victim->id, 'text' => 'Yo lo vi'])
            ->assertCreated()->json();
        $this->assertSame($me->id, Comment::find($comment['id'])->creator_id);

        $reply = $this->asUser($me)->postJson("/api/comments/{$comment['id']}/replies", ['creatorId' => $victim->id, 'text' => 'Sigue ahí'])
            ->assertCreated()->json();
        $this->assertSame($me->id, Comment::find($reply['id'])->creator_id);

        // ruta antigua con el id de otro usuario: la foto queda a mi nombre
        $this->asUser($me)->post("/api/reports/{$report['id']}/images/{$victim->id}", [
            'image' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        // ruta nueva sin id
        $this->asUser($me)->post("/api/reports/{$report['id']}/images", [
            'image' => UploadedFile::fake()->image('b.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(0, Image::where('uploaded_by', $victim->id)->count());
        $this->assertSame(2, Image::where('uploaded_by', $me->id)->count());

        $authority = $this->makeUser(2);
        $station = EmergencyStation::create(['name' => 'EPI', 'installation_type' => 'policia', 'latitude' => -17.7, 'longitude' => -63.1]);
        $this->asUser($authority)->postJson('/api/dispatches', [
            'reportId' => $report['id'], 'destinationId' => $station->id, 'userId' => $victim->id,
        ])->assertCreated();
        $this->assertSame($authority->id, Dispatch::first()->attended_by);
    }
}
