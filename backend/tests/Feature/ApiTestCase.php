<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, ReportTypeSeeder::class]);
    }

    /** En producción cada petición es nueva; en tests se reinicia el guard JWT. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function makeUser(int $roleId = 1, array $attrs = []): User
    {
        return User::factory()->role($roleId)->create($attrs);
    }

    protected function asUser(User $user): static
    {
        $token = app(JwtService::class)->issue($user, JwtService::ACCESS);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
