<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->admin()->active()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ADMIN@EXAMPLE.COM',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'admin@example.com')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role', 'status'],
            ])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_wrong_password_returns_generic_unauthorized_response_without_token(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'Las credenciales proporcionadas no son válidas.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_email_uses_same_response_as_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'Las credenciales proporcionadas no son válidas.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_receive_a_token(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
        ])->assertForbidden()->assertExactJson([
            'message' => 'El usuario está inactivo.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_valid_email_and_password_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_rejects_requests_without_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_public_data_for_authenticated_user(): void
    {
        $user = User::factory()->editor()->active()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'editor',
                    'status' => 'active',
                ],
            ]);
    }

    public function test_logout_revokes_only_current_token_and_it_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->withToken($currentToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        Auth::forgetGuards();

        $this->withToken($currentToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withToken($otherToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }
}
