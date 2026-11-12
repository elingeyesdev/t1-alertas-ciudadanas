<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PanelSessionAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::insert([
            ['id' => 1, 'name' => 'usuario'],
            ['id' => 3, 'name' => 'admin'],
        ]);
    }

    private function user(string $phone = '70000001', int $roleId = 3): User
    {
        return User::create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'phone' => $phone,
            'password' => 'secret123',
            'role_id' => $roleId,
        ]);
    }

    public function test_guest_is_redirected_to_panel_login_and_api_stays_json(): void
    {
        $this->get('/panel')->assertRedirect(route('panel.login'));
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_only_admin_can_login_and_invalid_credentials_are_generic(): void
    {
        $regular = $this->user('70000002', 1);
        $this->from('/panel/login')->post('/panel/login', ['phone' => $regular->phone, 'password' => 'secret123'])
            ->assertRedirect('/panel/login')->assertSessionHasErrors('phone');
        $this->assertGuest('web');

        Auth::guard('web')->login($regular);
        $this->get('/panel')->assertForbidden();

        $this->from('/panel/login')->post('/panel/login', ['phone' => '70000099', 'password' => 'wrong'])
            ->assertRedirect('/panel/login')->assertSessionHasErrors('phone')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_admin_login_rotates_session_and_remember_cookie_restores_authentication(): void
    {
        $admin = $this->user();
        $this->get('/panel/login')
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="remember"', false);
        $originalSessionId = $this->app['session']->getId();
        $originalToken = $this->app['session']->token();
        $recallerName = Auth::guard('web')->getRecallerName();

        $response = $this->withSession(['url.intended' => 'https://untrusted.example'])
            ->post('/panel/login', ['phone' => $admin->phone, 'password' => 'secret123', 'remember' => '1'])
            ->assertRedirect(route('panel.index'))
            ->assertCookie($recallerName);
        $this->assertNotSame($originalSessionId, $this->app['session']->getId());
        $this->assertNotSame($originalToken, $this->app['session']->token());
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNotEmpty($admin->fresh()->getRememberToken());
        $this->get('/panel')->assertOk()->assertSee('Ana Pérez');

        $recallerCookie = $response->getCookie($recallerName)->getValue();
        $this->flushSession();
        Auth::guard('web')->forgetUser();
        $this->withCookie($recallerName, $recallerCookie)->get('/panel')->assertOk();
        $this->assertAuthenticatedAs($admin, 'web');

        $beforeLogoutId = $this->app['session']->getId();
        $beforeLogoutToken = $this->app['session']->token();
        $this->post('/panel/logout')->assertRedirect(route('panel.login'));
        $this->assertNotSame($beforeLogoutId, $this->app['session']->getId());
        $this->assertNotSame($beforeLogoutToken, $this->app['session']->token());
        $this->assertGuest('web');
    }

    public function test_login_and_logout_reject_requests_without_a_valid_csrf_token(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'expected-token'])
            ->post('/panel/login', ['phone' => '70000001', 'password' => 'secret123'])
            ->assertStatus(419);
        $this->withSession(['_token' => 'expected-token'])
            ->post('/panel/logout', ['_token' => 'invalid-token'])
            ->assertStatus(419);
    }

    public function test_failed_login_attempts_are_throttled_by_phone_and_ip(): void
    {
        $admin = $this->user('70000111');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/panel/login', ['phone' => $admin->phone, 'password' => 'bad'])
                ->assertSessionHasErrors('phone');
        }
        $this->post('/panel/login', ['phone' => $admin->phone, 'password' => 'secret123'])
            ->assertSessionHasErrors('phone');
        $this->assertGuest('web');
        $this->assertTrue(RateLimiter::tooManyAttempts('panel-login:'.sha1('70000111|127.0.0.1'), 5));
    }
}
