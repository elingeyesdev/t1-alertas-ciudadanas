<?php

namespace Tests\Feature;

use App\Models\TrustContact;
use App\Models\User;
use App\Services\FcmService;
use App\Services\TrustCircle;
use Tests\Support\FakeFcm;

/** HU-25 (#13): círculo de confianza estilo Life360. */
class TrustCircleTest extends ApiTestCase
{
    private FakeFcm $fcm;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fcm = new FakeFcm;
        $this->app->instance(FcmService::class, $this->fcm);
        $this->owner = $this->makeUser(1, ['first_name' => 'Wilfredo', 'last_name' => 'Justiniano', 'phone' => '69122222']);
    }

    public function test_invite_code_lets_an_app_user_join_my_circle(): void
    {
        $member = $this->makeUser(1, ['first_name' => 'Ana', 'last_name' => 'Rojas', 'phone' => '70011122', 'fcm_token' => 'token-ana']);

        $invite = $this->asUser($this->owner)->postJson('/api/trust-circle/invites')->assertCreated();
        $code = $invite->json('code');
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $code);
        $this->assertStringContainsString($code, $invite->json('share_text'));

        // no puedo usar mi propio código
        $this->asUser($this->owner)->postJson('/api/trust-circle/join', ['code' => $code])->assertStatus(400);

        $this->asUser($member)->postJson('/api/trust-circle/join', ['code' => strtolower($code)])
            ->assertCreated()->assertJsonPath('owner_name', 'Wilfredo Justiniano');
        // el código es de un solo uso
        $this->asUser($this->makeUser(1))->postJson('/api/trust-circle/join', ['code' => $code])->assertStatus(400);

        $this->asUser($this->owner)->getJson('/api/trust-circle')->assertOk()
            ->assertJsonPath('contacts.0.name', 'Ana Rojas')
            ->assertJsonPath('contacts.0.has_app', true);
        $this->asUser($member)->getJson('/api/trust-circle')->assertOk()
            ->assertJsonPath('memberships.0.owner_name', 'Wilfredo Justiniano');
    }

    public function test_manual_contacts_are_limited_to_five_and_can_be_edited_and_removed(): void
    {
        foreach (range(1, 5) as $i) {
            $this->asUser($this->owner)->postJson('/api/trust-circle/contacts', ['name' => "Contacto {$i}", 'phone' => "7000000{$i}"])
                ->assertCreated()->assertJsonPath('has_app', false);
        }
        $this->asUser($this->owner)->postJson('/api/trust-circle/contacts', ['name' => 'Sexto', 'phone' => '70000006'])->assertStatus(400);
        $this->asUser($this->owner)->postJson('/api/trust-circle/invites')->assertStatus(400);

        $id = TrustContact::where('owner_id', $this->owner->id)->first()->id;
        $this->asUser($this->owner)->patchJson("/api/trust-circle/contacts/{$id}", ['name' => 'Mamá', 'phone' => '+591 7777-1234'])
            ->assertOk()->assertJsonPath('name', 'Mamá')->assertJsonPath('phone', '+59177771234');
        // teléfono repetido
        $this->asUser($this->owner)->postJson('/api/trust-circle/contacts', ['name' => 'X', 'phone' => '70000002'])->assertStatus(400);

        $this->asUser($this->owner)->deleteJson("/api/trust-circle/contacts/{$id}")->assertNoContent();
        $this->assertCount(4, $this->asUser($this->owner)->getJson('/api/trust-circle')->json('contacts'));
    }

    public function test_nobody_else_can_touch_my_contacts(): void
    {
        $id = $this->asUser($this->owner)->postJson('/api/trust-circle/contacts', ['name' => 'Mamá', 'phone' => '70000001'])->json('id');
        $intruder = $this->makeUser(1);

        $this->asUser($intruder)->patchJson("/api/trust-circle/contacts/{$id}", ['name' => 'hack'])->assertNotFound();
        $this->asUser($intruder)->deleteJson("/api/trust-circle/contacts/{$id}")->assertNotFound();
        $this->asUser($intruder)->deleteJson("/api/trust-circle/memberships/{$id}")->assertNotFound();
        $this->assertSame('Mamá', TrustContact::find($id)->name);
        $this->asUser($intruder)->getJson('/api/trust-circle')->assertJsonCount(0, 'contacts');
    }

    public function test_member_can_leave_a_circle(): void
    {
        $member = $this->makeUser(1);
        $code = $this->asUser($this->owner)->postJson('/api/trust-circle/invites')->json('code');
        $membershipId = $this->asUser($member)->postJson('/api/trust-circle/join', ['code' => $code])->json('id');

        $this->asUser($member)->deleteJson("/api/trust-circle/memberships/{$membershipId}")->assertNoContent();
        $this->asUser($this->owner)->getJson('/api/trust-circle')->assertJsonCount(0, 'contacts');
    }

    public function test_joining_upgrades_a_manual_contact_with_the_same_phone(): void
    {
        $this->asUser($this->owner)->postJson('/api/trust-circle/contacts', ['name' => 'Mamá', 'phone' => '70011122'])->assertCreated();
        $member = $this->makeUser(1, ['phone' => '70011122']);
        $code = $this->asUser($this->owner)->postJson('/api/trust-circle/invites')->json('code');
        $this->asUser($member)->postJson('/api/trust-circle/join', ['code' => $code])->assertCreated();

        $contacts = $this->asUser($this->owner)->getJson('/api/trust-circle')->json('contacts');
        $this->assertCount(1, $contacts);
        $this->assertTrue($contacts[0]['has_app']);
    }

    public function test_alert_pushes_app_contacts_and_returns_whatsapp_links_for_the_rest(): void
    {
        $member = $this->makeUser(1, ['fcm_token' => 'token-ana']);
        $code = $this->asUser($this->owner)->postJson('/api/trust-circle/invites')->json('code');
        $this->asUser($member)->postJson('/api/trust-circle/join', ['code' => $code])->assertCreated();
        $this->asUser($this->owner)->postJson('/api/trust-circle/contacts', ['name' => 'Papá', 'phone' => '7000 0009'])->assertCreated();

        $result = app(TrustCircle::class)->alert($this->owner, -17.7833, -63.1812, 7);

        $this->assertSame(1, $result['pushed']);
        $push = $this->fcm->ofType('trust_circle_sos')[0];
        $this->assertSame(['token-ana'], $push['tokens']);
        $this->assertSame('-17.7833', $push['data']['latitude']);
        $this->assertSame('7', $push['data']['reportId']);

        $this->assertCount(1, $result['whatsapp']);
        $this->assertSame('Papá', $result['whatsapp'][0]['name']);
        $this->assertStringStartsWith('https://wa.me/59170000009?text=', $result['whatsapp'][0]['url']);
        $this->assertStringContainsString(rawurlencode('https://maps.google.com/?q=-17.783300,-63.181200'), $result['whatsapp'][0]['url']);
    }
}
