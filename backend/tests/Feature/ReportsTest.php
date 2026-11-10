<?php

namespace Tests\Feature;

use App\Models\EmergencyStation;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReportsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createReport($user, array $over = []): array
    {
        return $this->asUser($user)->post('/api/reports', array_merge([
            'image' => UploadedFile::fake()->image('foto.jpg', 800, 600),
            'type' => 3,
            'description' => 'Incendio en una casa',
            'latitude' => '-17.7833',
            'longitude' => '-63.1821',
            'userId' => $user->id,
        ], $over), ['Accept' => 'application/json'])->assertCreated()->json();
    }

    public function test_create_report_and_queries(): void
    {
        $user = $this->makeUser(1);
        $report = $this->createReport($user);

        $this->assertSame($user->id, $report['creator']);
        $this->assertSame('Incendio', $report['type']['name']);
        $this->assertSame(10, $report['weight']);
        $this->assertSame('activo', $report['status']);
        $this->assertSame('Zona desconocida', $report['zone']);
        $this->assertSame([-63.1821, -17.7833], $report['coordinates']);
        $this->assertCount(1, $report['images']);
        $this->assertSame($user->id, $report['images'][0]['uploadedBy']['id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $report['created_at']);

        // descripción larga => +2 de peso
        $long = $this->createReport($user, ['description' => str_repeat('a', 120), 'type' => 6, 'zone' => 'Centro']);
        $this->assertSame(6, $long['weight']);
        $this->assertSame('Centro', $long['zone']);

        // validaciones
        $this->asUser($user)->post('/api/reports', ['type' => 0, 'description' => '  ', 'userId' => $user->id], ['Accept' => 'application/json'])
            ->assertStatus(400);
        $this->asUser($user)->post('/api/reports', [
            'image' => UploadedFile::fake()->image('a.jpg'), 'type' => 99, 'description' => 'x',
            'latitude' => 1, 'longitude' => 1, 'userId' => $user->id,
        ], ['Accept' => 'application/json'])->assertStatus(404)->assertJsonPath('message', 'Tipo no encontrado');

        $this->asUser($user)->getJson('/api/reports')->assertOk()->assertJsonCount(2);
        $this->asUser($user)->getJson("/api/reports/{$report['id']}")->assertOk()->assertJsonPath('id', $report['id']);
        $this->asUser($user)->getJson('/api/reports/999999')->assertStatus(404);
        $this->asUser($user)->getJson("/api/reports/user/{$user->id}")->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.id', $long['id']);

        // cercanos: 500 m incluye, 50 m desde otro punto (~1.5 km) excluye
        $this->asUser($user)->getJson('/api/reports/nearby?latitude=-17.786&longitude=-63.1821&radius=500')
            ->assertOk()->assertJsonCount(2);
        $this->asUser($user)->getJson('/api/reports/nearby?latitude=-17.797&longitude=-63.1821&radius=500')
            ->assertOk()->assertJsonCount(0);

        // similares: mismo tipo a <=100 m
        $this->asUser($user)->getJson("/api/reports/similars?userId={$user->id}&type=3&latitude=-17.7836&longitude=-63.1821")
            ->assertOk()->assertJsonCount(1)->assertJsonStructure([['id', 'description', 'coordinates', 'weight', 'verified', 'created_at', 'images', 'zone']]);
        $this->asUser($user)->getJson("/api/reports/similars?userId={$user->id}&type=1&latitude=-17.7836&longitude=-63.1821")
            ->assertOk()->assertJsonCount(0);

        // imagen adicional suma peso
        $this->asUser($user)->post("/api/reports/{$report['id']}/images/{$user->id}", [
            'image' => UploadedFile::fake()->image('b.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('weight', 11)->assertJsonCount(2, 'images');
    }

    public function test_verify_resolve_reactivate_remove(): void
    {
        $user = $this->makeUser(1);
        $authority = $this->makeUser(2);
        $id = $this->createReport($user)['id'];

        $this->asUser($user)->patchJson("/api/reports/{$id}/verify")->assertStatus(403);
        $this->asUser($authority)->patchJson("/api/reports/{$id}/verify")->assertOk()->assertJsonPath('verified', true);

        $this->asUser($user)->patchJson("/api/reports/{$id}/resolve")->assertOk();
        $this->assertSoftDeleted('reports', ['id' => $id, 'status' => 'resuelto']);
        $this->asUser($user)->getJson("/api/reports/{$id}")->assertStatus(404);

        $this->asUser($user)->patchJson("/api/reports/{$id}/reactivate")->assertOk();
        $this->assertDatabaseHas('reports', ['id' => $id, 'status' => 'activo', 'deleted_at' => null]);

        $this->asUser($user)->deleteJson("/api/reports/{$id}")->assertOk();
        $this->assertSoftDeleted('reports', ['id' => $id, 'status' => 'eliminado']);

        $this->asUser($user)->getJson('/api/reports')->assertJsonCount(0);
        $this->asUser($user)->getJson('/api/reports/with-deleted')->assertJsonCount(1)
            ->assertJsonPath('0.dispatches', []);
    }

    public function test_comments_and_replies(): void
    {
        $user = $this->makeUser(1);
        $id = $this->createReport($user)['id'];

        $c = $this->asUser($user)->postJson("/api/reports/{$id}/comments", ['creatorId' => $user->id, 'text' => 'Yo lo vi'])
            ->assertCreated()->assertJsonPath('creator.id', $user->id)->assertJsonPath('parent_comment', null)->json();

        $r = $this->asUser($user)->postJson("/api/comments/{$c['id']}/replies", ['creatorId' => $user->id, 'text' => 'Respuesta'])
            ->assertCreated()->assertJsonPath('parent_comment.id', $c['id'])->json();

        // no se puede responder una respuesta
        $this->asUser($user)->postJson("/api/comments/{$r['id']}/replies", ['creatorId' => $user->id, 'text' => 'x'])
            ->assertStatus(400);
        $this->asUser($user)->postJson("/api/reports/{$id}/comments", ['creatorId' => $user->id, 'text' => str_repeat('a', 251)])
            ->assertStatus(400);

        $this->asUser($user)->getJson("/api/reports/{$id}/comments")->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.replies_count', 1);
        $this->asUser($user)->getJson("/api/comments/{$c['id']}/replies")->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.text', 'Respuesta');
    }

    public function test_stations_and_dispatches(): void
    {
        $user = $this->makeUser(2);
        $reportId = $this->createReport($this->makeUser(1))['id'];

        $station = $this->asUser($user)->postJson('/api/emergency-stations', [
            'name' => 'UTOP Norte', 'installation_type' => 'policia', 'latitude' => -17.75, 'longitude' => -63.18,
        ])->assertCreated()->assertJsonPath('coordinates', [-63.18, -17.75])->json();
        $this->asUser($user)->postJson('/api/emergency-stations', ['name' => 'x', 'installation_type' => 'otro', 'latitude' => 1, 'longitude' => 1])
            ->assertStatus(400);
        $this->asUser($user)->patchJson("/api/emergency-stations/{$station['id']}", ['name' => 'UTOP Norte 2'])
            ->assertOk()->assertJsonPath('name', 'UTOP Norte 2');
        $this->asUser($user)->getJson('/api/emergency-stations')->assertOk()->assertJsonCount(1);

        $dispatch = $this->asUser($user)->postJson('/api/dispatches', [
            'reportId' => $reportId, 'destinationId' => $station['id'], 'userId' => $user->id,
        ])->assertCreated()->assertJsonPath('state', 'en_curso')->assertJsonPath('attended_by.id', $user->id)->json();

        $this->asUser($user)->patchJson("/api/dispatches/{$dispatch['id']}/state", ['state' => 'completado'])
            ->assertOk()->assertJsonPath('state', 'completado');
        $this->asUser($user)->patchJson("/api/dispatches/{$dispatch['id']}/state", ['state' => 'otro'])->assertStatus(400);

        $this->asUser($user)->getJson('/api/reports/with-deleted')
            ->assertJsonPath('0.dispatches.0.state', 'completado')
            ->assertJsonPath('0.dispatches.0.attended_by.id', $user->id);

        $this->asUser($user)->deleteJson("/api/emergency-stations/{$station['id']}")->assertOk();
        $this->assertSoftDeleted(EmergencyStation::class, ['id' => $station['id']]);
    }

    public function test_notify_route_and_cooldown(): void
    {
        $authority = $this->makeUser(2);
        $near = $this->makeUser(1, ['fcm_token' => 'a', 'last_latitude' => -17.7805, 'last_longitude' => -63.18]);
        $far = $this->makeUser(1, ['fcm_token' => 'b', 'last_latitude' => -17.80, 'last_longitude' => -63.18]);

        $route = [['lat' => -17.78, 'lng' => -63.19], ['lat' => -17.78, 'lng' => -63.17]];

        $this->asUser($near)->postJson('/api/notifications/notify-route', ['route' => $route])->assertStatus(403);

        $this->asUser($authority)->postJson('/api/notifications/notify-route', ['route' => $route, 'incidentType' => 'Incendio'])
            ->assertCreated()->assertExactJson(['notified' => 1, 'skippedCooldown' => 0]);
        $this->asUser($authority)->postJson('/api/notifications/notify-route', ['route' => $route])
            ->assertCreated()->assertExactJson(['notified' => 0, 'skippedCooldown' => 1]);
        $this->asUser($authority)->postJson('/api/notifications/notify-route', ['route' => [$route[0]]])->assertStatus(400);
    }

    public function test_trackings(): void
    {
        $user = $this->makeUser(2);
        $this->asUser($user)->postJson('/api/trackings/start', ['userId' => 'u1', 'lat' => -17.7, 'lng' => -63.1])
            ->assertCreated()->assertJsonPath('id', 'u1')->assertJsonPath('lat', -17.7);
        $this->asUser($user)->getJson('/api/trackings')->assertOk()->assertJsonCount(1);
        $this->asUser($user)->postJson('/api/trackings/stop', ['userId' => 'u1'])->assertCreated();
        $this->asUser($user)->getJson('/api/trackings')->assertOk()->assertJsonCount(0);
    }

    public function test_expire_command_and_image_delete(): void
    {
        $user = $this->makeUser(1);
        $admin = $this->makeUser(3);
        $report = $this->createReport($user);

        $this->asUser($user)->deleteJson("/api/images/{$report['images'][0]['id']}")->assertStatus(403);
        $this->asUser($admin)->deleteJson("/api/images/{$report['images'][0]['id']}")->assertOk();
        $this->asUser($admin)->deleteJson("/api/images/{$report['images'][0]['id']}")->assertStatus(404);

        Report::whereKey($report['id'])->update(['expires_at' => now()->subMinute()]);
        $this->artisan('reports:expire')->assertSuccessful();
        $this->assertSoftDeleted('reports', ['id' => $report['id'], 'status' => 'vencido']);
    }

    public function test_report_without_image_and_list_filters(): void
    {
        $user = $this->makeUser(1);
        $admin = $this->makeUser(3);

        $sinFoto = $this->asUser($user)->postJson('/api/reports', [
            'type' => 1, 'description' => 'Choque sin foto', 'latitude' => -17.78, 'longitude' => -63.18,
            'userId' => $user->id, 'zone' => 'Centro',
        ])->assertCreated()->assertJsonCount(0, 'images')->json();

        $this->createReport($user, ['type' => 3, 'zone' => 'Norte']);
        $this->asUser($admin)->patchJson("/api/reports/{$sinFoto['id']}/verify")->assertOk();

        $this->asUser($user)->getJson('/api/reports?typeId=3')->assertOk()->assertJsonCount(1);
        $this->asUser($user)->getJson('/api/reports?category=incendios')->assertOk()->assertJsonCount(1);
        $this->asUser($user)->getJson('/api/reports?status=verified')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $sinFoto['id']);
        $this->asUser($user)->getJson('/api/reports?zone=Centro')->assertOk()->assertJsonCount(1);
        $this->asUser($user)->getJson('/api/reports?search=choque')->assertOk()->assertJsonCount(1);
        $this->asUser($user)->getJson('/api/reports?from='.now()->subDay()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonCount(2);

        $this->asUser($user)->deleteJson("/api/reports/{$sinFoto['id']}")->assertOk();
        $this->asUser($user)->getJson('/api/reports')->assertJsonCount(1);
        $this->asUser($user)->getJson('/api/reports?includeDeleted=true')->assertJsonCount(2);

        // similars sin userId (como lo llama la web)
        $this->asUser($user)->getJson('/api/reports/similars?type=3&latitude=-17.7833&longitude=-63.1821')->assertOk()->assertJsonCount(1);
    }

    public function test_admin_creates_user_with_post_users(): void
    {
        $admin = $this->makeUser(3);
        $payload = ['first_name' => 'Ana', 'last_name' => 'Paz', 'phone' => '77777777', 'password' => 'clave123', 'roleId' => 2];

        $this->asUser($this->makeUser(1))->postJson('/api/users', $payload)->assertStatus(403);
        $this->asUser($admin)->postJson('/api/users', $payload)->assertCreated()->assertJsonPath('role.name', 'autoridad');
    }
}
