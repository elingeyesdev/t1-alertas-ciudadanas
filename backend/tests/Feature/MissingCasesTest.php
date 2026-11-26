<?php

namespace Tests\Feature;

use App\Models\MissingCase;
use App\Models\User;
use App\Services\FcmService;
use App\Services\JwtService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeFcm;
use Tests\TestCase;

class MissingCasesTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/missing-cases';

    private const PANEL = '/panel/desaparecidos';

    private const ZONE = ['latitude' => -17.7833, 'longitude' => -63.1821, 'radius_m' => 2000];

    private User $citizen;

    private User $admin;

    private FakeFcm $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->withoutVite();
        Storage::fake('local');
        $this->citizen = User::factory()->role(1)->create([
            'last_latitude' => -17.7834,
            'last_longitude' => -63.1821,
            'location_sharing' => true,
            'fcm_token' => 'nearby-citizen',
        ]);
        $this->admin = User::factory()->role(3)->create();
        $this->fcm = new FakeFcm;
        $this->app->instance(FcmService::class, $this->fcm);
    }

    public function test_suggestion_is_private_until_approval_then_can_be_marked_found(): void
    {
        $outside = User::factory()->role(1)->create(['last_latitude' => -18, 'last_longitude' => -63, 'location_sharing' => true, 'fcm_token' => 'outside']);
        User::factory()->role(1)->create(['last_latitude' => -17.7834, 'last_longitude' => -63.1821, 'location_sharing' => true, 'fcm_token' => 'neighbor']);
        User::factory()->role(1)->create(['last_latitude' => -17.7834, 'last_longitude' => -63.1821, 'location_sharing' => false, 'fcm_token' => 'private']);
        User::factory()->role(2)->create(['last_latitude' => -17.7834, 'last_longitude' => -63.1821, 'location_sharing' => true, 'fcm_token' => 'staff']);

        $id = $this->asApiUser($this->citizen)->post(self::API.'/suggestions', $this->payload([
            'status' => 'published',
            'photos' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.jpg')],
        ]))->assertCreated()->assertJsonPath('status', 'pending')->json('id');

        $case = MissingCase::findOrFail($id);
        $this->assertSame($this->citizen->id, $case->submitted_by);
        $this->assertSame(self::ZONE['latitude'], $case->latitude);
        $this->assertSame(self::ZONE['longitude'], $case->longitude);
        Storage::disk('local')->assertExists($case->photos);
        $this->getJson(self::API)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/mine')->assertOk()->assertJsonPath('data.0.status', 'pending');
        $this->getJson(self::API.'/'.$id)->assertOk()->assertJsonPath('status', 'pending');
        $this->getJson(self::API.'/'.$id.'/photos/0')->assertOk();
        $this->asApiUser($outside)->getJson(self::API.'/mine')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/'.$id)->assertNotFound();
        $this->getJson(self::API.'/'.$id.'/photos/0')->assertNotFound();
        $this->getJson(self::API.'/map')->assertExactJson([]);
        $this->assertSame([], $this->fcm->sent);

        $panel = self::PANEL.'/'.$id;
        $this->actingAs($this->admin, 'web')->get($panel)->assertOk()
            ->assertSee('Aprobar y publicar')->assertSee('Teléfono de referencia')
            ->assertSee('value="-17.7833"', false);
        $this->get($panel.'/photos/0')->assertOk()->assertSee('Volver al caso')
            ->assertSee('href="'.url($panel).'"', false)
            ->assertSee('src="'.url($panel.'/photos/0/image').'"', false);
        $this->get($panel.'/photos/0/image')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($panel.'/photos/9')->assertNotFound();
        $this->from($panel)->patch($panel.'/aprobar', self::ZONE)->assertRedirect($panel)->assertSessionHas('success');
        $this->patch($panel.'/aprobar', self::ZONE)->assertSessionHas('error');
        $this->assertSame($this->admin->id, $case->fresh()->published_by);
        $notifications = $this->fcm->ofType('missing_case');
        $this->assertCount(1, $notifications);
        $this->assertSame(['neighbor'], $notifications[0]['tokens']);
        $this->assertSame((string) $id, $notifications[0]['data']['missingCaseId']);
        $ownerNotifications = $this->fcm->ofType('missing_case_update');
        $this->assertCount(1, $ownerNotifications);
        $this->assertSame(['nearby-citizen'], $ownerNotifications[0]['tokens']);
        $this->assertSame('published', $ownerNotifications[0]['data']['update']);

        $this->asApiUser($this->citizen)->getJson(self::API.'?kind=person')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.photo_count', 2)
            ->assertJsonPath('data.0.reference_phone', '+59170000002');
        $this->getJson(self::API.'?kind=pet')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/map')->assertOk()->assertJsonPath('0.id', $id)
            ->assertJsonPath('0.latitude', self::ZONE['latitude']);
        $this->getJson(self::API.'/'.$id.'/photos/1')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson(self::API.'/'.$id.'/photos/2')->assertNotFound();

        $this->actingAs($this->admin, 'web')->patch($panel.'/encontrado')->assertSessionHas('success');
        $this->asApiUser($this->citizen)->getJson(self::API.'/'.$id)->assertOk()
            ->assertJsonPath('status', 'found')->assertJsonMissingPath('reference_phone');
        $this->getJson(self::API.'?kind=person')->assertOk()->assertJsonPath('data.0.status', 'found')
            ->assertJsonMissingPath('data.0.reference_phone');
        $this->getJson(self::API.'/map')->assertJsonPath('0.status', 'found');
        $this->assertSame(['published', 'found'], array_column(array_column($this->fcm->ofType('missing_case_update'), 'data'), 'update'));
    }

    public function test_support_can_reject_a_suggestion_and_publish_a_case_directly(): void
    {
        $id = $this->asApiUser($this->citizen)->post(self::API.'/suggestions', $this->payload())
            ->assertCreated()->json('id');

        $this->actingAs($this->admin, 'web')->patch(self::PANEL.'/'.$id.'/rechazar')->assertSessionHas('success');
        $this->patch(self::PANEL.'/'.$id.'/aprobar', self::ZONE)->assertSessionHas('error');
        $this->assertSame([], $this->fcm->ofType('missing_case'));
        $this->assertSame('rejected', $this->fcm->ofType('missing_case_update')[0]['data']['update']);
        $this->get(self::PANEL.'?status=rejected')->assertOk()->assertSee('Caso de prueba');
        $this->get(self::PANEL.'/crear')->assertOk()->assertSee('Publicar y avisar a la zona');
        $this->post(self::PANEL, $this->payload(['kind' => 'pet', 'name' => 'Mascota de prueba']) + self::ZONE)
            ->assertRedirect()->assertSessionHas('success');

        $case = MissingCase::where('kind', 'pet')->sole();
        $this->assertSame('published', $case->status);
        $this->assertNull($case->submitted_by);
        $this->assertSame($this->admin->id, $case->published_by);
        $this->assertCount(1, $this->fcm->ofType('missing_case'));
        $this->asApiUser($this->citizen)->getJson(self::API.'/'.$id)->assertOk()->assertJsonPath('status', 'rejected');
        $this->getJson(self::API.'/mine')->assertJsonPath('data.0.status', 'rejected');
        $this->getJson(self::API.'/'.$id.'/photos/0')->assertOk();
        $this->getJson(self::API.'?kind=pet')->assertOk()->assertJsonPath('data.0.id', $case->id);
    }

    public function test_video_appears_in_case_evidence_and_the_owner_gets_updates_without_sharing_location(): void
    {
        $this->citizen->update(['location_sharing' => false]);
        $id = $this->asApiUser($this->citizen)->post(self::API.'/suggestions', $this->payload())
            ->assertCreated()->json('id');
        $panel = self::PANEL.'/'.$id;
        $case = self::API.'/'.$id;
        $endpoint = $case.'/contributions';
        $this->actingAs($this->admin, 'web')->patch($panel.'/aprobar', self::ZONE)->assertSessionHas('success');
        $this->assertSame([], $this->fcm->ofType('missing_case'));

        $contributor = User::factory()->role(1)->create();
        $this->asApiUser($contributor)->post($endpoint, ['video' => UploadedFile::fake()->create('bad.txt')])
            ->assertBadRequest()->assertJsonStructure(['errors' => ['video']]);
        $this->post($endpoint, [
            'video' => UploadedFile::fake()->create('large.mp4', 19000, 'video/mp4'),
            'photos' => [UploadedFile::fake()->image('foto.png')->size(3000)],
        ])->assertBadRequest()->assertJsonStructure(['errors' => ['video']]);
        $contributionId = $this->post($endpoint, [
            'video' => UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
        ])->assertCreated()->assertJsonPath('has_video', true)->json('id');
        $this->getJson($case)->assertOk()
            ->assertJsonPath('evidence.0.type', 'photo')
            ->assertJsonPath('evidence.1.type', 'video')
            ->assertJsonPath('evidence.1.contribution_id', $contributionId);

        $video = $endpoint.'/'.$contributionId.'/video';
        $this->get($video)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson($video)->assertUnauthorized();
        $this->actingAs($this->admin, 'web')->get($panel)->assertOk()->assertSee('Video del aporte');

        $updates = $this->fcm->ofType('missing_case_update');
        $this->assertSame(['published', 'contribution'], array_column(array_column($updates, 'data'), 'update'));
        $this->assertSame([['nearby-citizen'], ['nearby-citizen']], array_column($updates, 'tokens'));
    }

    public function test_permissions_and_form_validation_are_enforced(): void
    {
        $this->getJson(self::API)->assertUnauthorized();
        $this->asApiUser($this->admin)->post(self::API.'/suggestions', $this->payload())->assertForbidden();
        $this->actingAs($this->citizen, 'web')->get(self::PANEL)->assertForbidden();
        $this->post(self::PANEL, $this->payload() + self::ZONE)->assertForbidden();

        $this->asApiUser($this->citizen)->post(self::API.'/suggestions', $this->payload([
            'missing_on' => now('America/La_Paz')->addDay()->toDateString(),
            'reference_phone' => '+( )--',
            'latitude' => null,
            'longitude' => 181,
            'photos' => [UploadedFile::fake()->image('large.png')->size(5121), UploadedFile::fake()->create('invalid.pdf')],
        ]))->assertBadRequest()->assertJsonStructure(['errors' => ['missing_on', 'reference_phone', 'latitude', 'longitude', 'photos.0', 'photos.1']]);
        $this->assertDatabaseCount('missing_cases', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_contributions_can_include_comments_and_photos_and_remain_visible_after_closure(): void
    {
        $id = $this->asApiUser($this->citizen)->post(self::API.'/suggestions', $this->payload())->assertCreated()->json('id');
        $panel = self::PANEL.'/'.$id;
        $endpoint = self::API.'/'.$id.'/contributions';

        $this->getJson($endpoint)->assertNotFound();
        $this->postJson($endpoint, ['message' => 'Avistamiento'])->assertNotFound();
        $this->actingAs($this->admin, 'web')->patch($panel.'/aprobar', self::ZONE)->assertSessionHas('success');

        $this->asApiUser($this->citizen)->postJson($endpoint, [])->assertBadRequest();
        $comment = $this->postJson($endpoint, ['message' => 'Avistamiento de prueba'])
            ->assertCreated()->assertJsonPath('photo_count', 0)->json('id');
        $photo = $this->post($endpoint, ['photos' => [UploadedFile::fake()->image('aporte.png')]])
            ->assertCreated()->assertJsonPath('photo_count', 1)->json('id');
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.0.id', $photo)
            ->assertJsonPath('data.1.id', $comment)->assertJsonPath('data.1.message', 'Avistamiento de prueba');
        $this->getJson($endpoint.'/'.$photo.'/photos/0')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson($endpoint.'/'.$comment.'/photos/0')->assertNotFound();

        $this->actingAs($this->admin, 'web')->get('/panel/mapa/datos')->assertOk()
            ->assertJsonPath('missing_cases.0.url', $panel);
        $this->get($panel)->assertOk()->assertSee('Avistamiento de prueba');
        $preview = $panel.'/contributions/'.$photo.'/photos/0';
        $this->get($preview)->assertOk()->assertSee('Volver al caso')
            ->assertSee('href="'.url($panel).'"', false)
            ->assertSee('src="'.url($preview.'/image').'"', false);
        $this->get($preview.'/image')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->patch($panel.'/encontrado')->assertSessionHas('success');

        $this->asApiUser($this->citizen)->getJson($endpoint)->assertOk()->assertJsonCount(2, 'data');
        $this->postJson($endpoint, ['message' => 'Otro aviso'])->assertConflict();
    }

    private function asApiUser(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $token = app(JwtService::class)->issue($user, JwtService::ACCESS);

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'person',
            'name' => 'Caso de prueba',
            'place' => 'Plaza de prueba',
            'missing_on' => now('America/La_Paz')->toDateString(),
            'description' => 'Descripción de prueba.',
            'reference_phone' => '+591 (7000) 0002',
            'latitude' => self::ZONE['latitude'],
            'longitude' => self::ZONE['longitude'],
            'photos' => [UploadedFile::fake()->image('foto.png')],
        ];
    }
}
