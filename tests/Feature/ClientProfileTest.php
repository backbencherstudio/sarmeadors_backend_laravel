<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_profile_show_returns_screen_ready_payload(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Manhattan']);
        $client->update(['location_id' => [$location->id]]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/profile');

        $response
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.first_name', 'Jenny')
            ->assertJsonPath('data.last_name', 'Wilson')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.mobile', '+1433467689')
            ->assertJsonPath('data.locations.0.name', 'Manhattan');
    }

    public function test_client_can_update_profile_with_image_and_locations(): void
    {
        Storage::fake('public');

        [$agency, $user, $client] = $this->createClientScenario();

        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Manhattan']);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->put('/api/client/profile', [
                'first_name' => 'Jennifer',
                'last_name' => 'Wilson',
                'mobile' => '+15550001111',
                'location_id' => [$location->id],
                'image' => UploadedFile::fake()->image('avatar.png'),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Jennifer')
            ->assertJsonPath('data.mobile', '+15550001111')
            ->assertJsonPath('data.locations.0.id', $location->id);

        $this->assertNotNull($response->json('data.image_url'));
        $this->assertSame('Jennifer', $user->fresh()->first_name);
        $this->assertSame('Jennifer', $client->fresh()->first_name);
        Storage::disk('public')->assertExists($client->fresh()->image);
    }

    public function test_profile_update_rejects_locations_from_another_agency(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignLocation = Location::create(['agency_id' => $otherAgency->id, 'location' => 'Foreign']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson('/api/client/profile', [
                'location_id' => [$foreignLocation->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed');
    }

    public function test_client_can_update_password_with_valid_rules(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $wrongCurrent = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson('/api/client/profile/password', [
                'current_password' => 'not-the-password',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ]);

        $wrongCurrent
            ->assertStatus(422)
            ->assertJsonPath('message', 'Current password is incorrect.');

        $weakPassword = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson('/api/client/profile/password', [
                'current_password' => 'password',
                'password' => 'onlyletters',
                'password_confirmation' => 'onlyletters',
            ]);

        $weakPassword
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson('/api/client/profile/password', [
                'current_password' => 'password',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_client_can_delete_account_with_password_confirmation(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson('/api/client/profile', [
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Password is incorrect.');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson('/api/client/profile', [
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    private function createClientScenario(): array
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
            'first_name' => 'Jenny',
            'last_name' => 'Wilson',
            'password' => Hash::make('password'),
        ]);

        Role::create([
            'name' => 'client',
            'guard_name' => 'web',
        ]);

        $user->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jenny',
            'last_name' => 'Wilson',
            'email' => $user->email,
            'mobile' => '+1433467689',
        ]);

        return [$agency, $user, $client];
    }
}
