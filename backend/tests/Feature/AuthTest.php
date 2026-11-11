<?php

namespace Tests\Feature;

class AuthTest extends ApiTestCase
{
    public function test_register_login_me_refresh_logout(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'first_name' => '  Juan ', 'last_name' => 'PEREZ', 'phone' => '71234567',
            'password' => 'secreto1', 'roleId' => 1,
        ])->assertCreated()
            ->assertJsonPath('user.first_name', 'juan')
            ->assertJsonPath('user.last_name', 'perez')
            ->assertJsonPath('user.role.name', 'usuario')
            ->assertJsonPath('user.authority_profile', null);
        $this->assertNotEmpty($res->json('access_token'));

        // teléfono repetido
        $this->postJson('/api/auth/register', [
            'first_name' => 'a', 'last_name' => 'b', 'phone' => '71234567', 'password' => 'secreto1', 'roleId' => 1,
        ])->assertStatus(400)->assertJsonPath('statusCode', 400);

        // login incorrecto
        $this->postJson('/api/auth/login', ['phone' => '71234567', 'password' => 'mala123'])
            ->assertStatus(400)->assertJsonPath('message', 'Contrasena Incorrecta');
        $this->postJson('/api/auth/login', ['phone' => '79999999', 'password' => 'x'])
            ->assertStatus(404);

        $login = $this->postJson('/api/auth/login', ['phone' => '71234567', 'password' => 'secreto1'])
            ->assertCreated()->json();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/auth/me')->assertOk()->assertJsonPath('phone', '71234567');

        // el refresh token NO sirve como access token
        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('statusCode', 401);

        $new = $this->postJson('/api/auth/refresh', ['refresh_token' => $login['refresh_token']])
            ->assertCreated()->json('access_token');
        $this->withHeader('Authorization', "Bearer {$new}")->getJson('/api/auth/me')->assertOk();

        $this->withHeader('Authorization', "Bearer {$new}")->postJson('/api/auth/logout')
            ->assertCreated()->assertJsonPath('message', 'Logout exitoso');

        // tras logout el refresh token queda invalidado
        $this->postJson('/api/auth/refresh', ['refresh_token' => $login['refresh_token']])->assertStatus(400);
    }

    public function test_validation_errors_use_nest_format(): void
    {
        $this->postJson('/api/auth/register', ['phone' => '123', 'password' => 'a b'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Bad Request')
            ->assertJsonStructure(['statusCode', 'message', 'error', 'errors']);
    }

    public function test_protected_routes_require_token_and_roles(): void
    {
        $this->getJson('/api/users')->assertStatus(401);

        $user = $this->makeUser(1);
        $admin = $this->makeUser(3);

        $this->asUser($user)->getJson('/api')->assertStatus(403);
        $this->asUser($admin)->getJson('/api')->assertOk()->assertSee('Hello World!');
    }
}
