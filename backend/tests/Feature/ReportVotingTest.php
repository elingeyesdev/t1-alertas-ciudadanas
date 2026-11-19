<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\FcmService;

class ReportVotingTest extends ApiTestCase
{
    private function report(User $author): Report
    {
        return Report::create([
            'creator_id' => $author->id,
            'report_type_id' => 1,
            'description' => 'Incidente de prueba',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'weight' => 0,
            'expires_at' => now()->addHour(),
        ])->refresh();
    }

    private function neighbor(int $role = 1, array $attributes = []): User
    {
        return $this->makeUser($role, $attributes + [
            'last_latitude' => -17.78,
            'last_longitude' => -63.18,
        ]);
    }

    public function test_votes_update_weight_once_and_remain_visible_after_verification(): void
    {
        $report = $this->report($this->neighbor());
        $negative = $this->neighbor();
        $positive = $this->neighbor();
        $authority = $this->neighbor(2);
        $uri = "/api/reports/{$report->id}";

        $this->asUser($negative)->postJson("$uri/votes", ['vote' => 'false'])
            ->assertOk()->assertJsonPath('false', 1)->assertJsonPath('weight', 0);
        $this->asUser($positive)->postJson("$uri/votes", ['vote' => 'seen'])
            ->assertOk()->assertJsonPath('seen', 1)->assertJsonPath('weight', 1);
        $this->postJson("$uri/votes", ['vote' => 'seen'])
            ->assertOk()->assertJsonPath('weight', 1);
        $this->postJson("$uri/votes", ['vote' => 'false'])->assertOk()
            ->assertJsonPath('seen', 0)->assertJsonPath('false', 2)
            ->assertJsonPath('weight', 0);
        $this->postJson("$uri/votes", ['vote' => 'seen'])->assertOk()
            ->assertJsonPath('seen', 1)->assertJsonPath('false', 1)
            ->assertJsonPath('weight', 2);

        $this->asUser($authority)->patchJson("$uri/verify")->assertOk();
        $this->asUser($positive)->getJson($uri)->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('voting.own_vote', 'seen')
            ->assertJsonPath('voting.seen', 1)
            ->assertJsonPath('voting.false', 1);
        $this->postJson("$uri/votes", ['vote' => 'false'])->assertConflict();
        $this->asUser($this->neighbor())->postJson("$uri/votes", ['vote' => 'seen'])->assertConflict();
        $this->assertDatabaseCount('report_votes', 2);
    }

    public function test_only_nearby_citizens_other_than_the_author_can_vote(): void
    {
        $author = $this->neighbor();
        $report = $this->report($author);
        $uri = "/api/reports/{$report->id}";

        $this->postJson("$uri/votes", ['vote' => 'seen'])->assertUnauthorized();
        foreach ([$author, $this->neighbor(2), $this->neighbor(1, ['last_latitude' => -18])] as $user) {
            $this->asUser($user)->postJson("$uri/votes", ['vote' => 'seen'])->assertForbidden();
        }

        $this->asUser($this->makeUser())->getJson($uri)
            ->assertJsonPath('voting.reason', 'missing_location');
        $this->postJson("$uri/votes", ['vote' => 'seen'])->assertForbidden();

        $report->update(['status' => 'resuelto']);
        $this->asUser($this->neighbor())->postJson("$uri/votes", ['vote' => 'seen'])->assertConflict();
        $this->assertDatabaseCount('report_votes', 0);
    }

    public function test_nearby_notice_only_targets_eligible_neighbors_and_links_the_report(): void
    {
        $author = $this->neighbor(1, ['fcm_token' => 'author']);
        $this->neighbor(1, ['fcm_token' => 'nearby']);
        $this->neighbor(1, ['fcm_token' => 'far', 'last_latitude' => -18]);
        $this->neighbor(2, ['fcm_token' => 'authority']);
        $this->mock(FcmService::class)->shouldReceive('sendToTokens')->once()
            ->withArgs(fn ($tokens, $title, $body, $data) =>
                $tokens === ['nearby'] && $data['type'] === 'nearby_report' && (int) $data['reportId'] > 0)
            ->andReturn([]);

        $this->asUser($author)->postJson('/api/reports', [
            'type' => 1,
            'description' => 'Incidente de prueba',
            'latitude' => -17.78,
            'longitude' => -63.18,
        ])->assertCreated();
    }

    public function test_fire_and_disturbance_notify_neighbors_with_their_saved_sound_preference(): void
    {
        $author = $this->neighbor(1, ['fcm_token' => 'author']);
        $nearby = $this->neighbor(1, ['fcm_token' => 'audible', 'last_latitude' => -17.77866]);
        $muted = $this->neighbor(1, ['fcm_token' => 'muted']);
        $this->neighbor(1, ['fcm_token' => 'outside', 'last_latitude' => -17.77864]);

        $this->asUser($nearby)->getJson('/api/auth/emergency-siren')
            ->assertOk()->assertJsonPath('enabled', true);
        $this->asUser($muted)->putJson('/api/auth/emergency-siren', ['enabled' => false])
            ->assertOk()->assertJsonPath('enabled', false);
        $this->getJson('/api/auth/emergency-siren')->assertOk()->assertJsonPath('enabled', false);

        $fcm = $this->mock(FcmService::class);
        foreach (['siren' => 'audible', 'silent' => 'muted'] as $mode => $token) {
            $fcm->shouldReceive('sendToTokens')->twice()->withArgs(
                fn ($tokens, $title, $body, $data) => $tokens === [$token]
                    && $data['type'] === 'nearby_report' && $data['emergency_sound'] === $mode
                    && (int) $data['reportId'] > 0,
            )->andReturn([]);
        }

        foreach (['Incendio', 'Disturbio'] as $name) {
            $type = \App\Models\ReportType::create(['name' => $name, 'base_weight' => 5]);
            $this->asUser($author)->postJson('/api/reports', [
                'type' => $type->id,
                'description' => 'Emergencia de prueba',
                'latitude' => -17.78,
                'longitude' => -63.18,
            ])->assertCreated();
        }
    }
}
