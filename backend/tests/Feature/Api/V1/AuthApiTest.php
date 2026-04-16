<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\InteractsWithApiTokens;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use InteractsWithApiTokens;

    public function test_register_creates_organizer(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Organizer User',
            'email' => 'organizer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'organizer',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'meta' => ['token']])
            ->assertJsonPath('data.role', 'organizer');

        $this->assertDatabaseHas('users', [
            'email' => 'organizer@example.com',
            'role' => 'organizer',
        ]);
    }

    public function test_register_creates_participant(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Participant User',
            'email' => 'participant@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'participant',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'participant');

        $this->assertDatabaseHas('users', [
            'email' => 'participant@example.com',
            'role' => 'participant',
        ]);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->participant()->create([
            'email' => 'login@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'meta' => ['token']])
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->participant()->create([
            'email' => 'exists@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'exists@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->participant()->create();

        $tokenA = $user->createToken('auth')->plainTextToken;
        $tokenB = $user->createToken('auth')->plainTextToken;

        $this->flushHeaders()->withToken($tokenA)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNull(PersonalAccessToken::findToken($tokenA));
        $this->assertNotNull(PersonalAccessToken::findToken($tokenB));

        $this->flushHeaders()->withToken($tokenB)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertNull(PersonalAccessToken::findToken($tokenA));
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->participant()->create([
            'name' => 'Me Test',
            'email' => 'me@example.com',
        ]);

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$this->bearer($user),
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonPath('data.name', 'Me Test')
            ->assertJsonPath('data.role', 'participant');
    }

    public function test_me_without_token_returns_unauthorized(): void
    {
        $this->getJson('/api/v1/me', [
            'Accept' => 'application/json',
        ])->assertUnauthorized();
    }
}
