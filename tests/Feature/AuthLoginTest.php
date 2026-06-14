<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_returns_the_users_single_role(): void
    {
        $user = $this->createUserWithRole('client');

        $response = $this
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'client');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_response_omits_password_and_roles_relation(): void
    {
        $user = $this->createUserWithRole('client');

        $response = $this
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertArrayNotHasKey('roles', $response->json('user'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = $this->createUserWithRole('client');

        $this
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    private function createUserWithRole(string $role): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ]);

        Role::create([
            'name' => $role,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
