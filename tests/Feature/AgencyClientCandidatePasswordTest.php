<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\ClientSecondaryLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyClientCandidatePasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_gets_a_default_password_portal_account_on_creation(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $user = $client->user;
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue(Hash::check(Client::DEFAULT_PASSWORD, $user->password));

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/clients/{$client->id}/password")
            ->assertOk()
            ->assertJsonPath('data.has_account', true)
            ->assertJsonCount(0, 'data.secondary_logins');

        $loginResponse = $this->postJson('/api/login', [
            'email' => $client->email,
            'password' => Client::DEFAULT_PASSWORD,
        ]);
        $loginResponse->assertOk();
        $this->assertSame($user->id, $loginResponse->json('user.id'));
    }

    public function test_update_password_overwrites_the_default_password(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'last_name' => 'Candra', 'email' => 'pristia@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/clients/{$client->id}/password", ['password' => 'Secret123'])
            ->assertOk();

        $user = $client->user()->first();
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue(Hash::check('Secret123', $user->password));
        $this->assertFalse(Hash::check(Client::DEFAULT_PASSWORD, $user->password));
    }

    public function test_update_password_rejects_a_weak_password(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/clients/{$client->id}/password", ['password' => 'allletters'])
            ->assertStatus(422);
    }

    public function test_secondary_login_can_be_added_and_authenticates_as_the_same_primary_user(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'last_name' => 'Candra', 'email' => 'pristia@example.com']);

        $storeResponse = $this->actingAsAgency($admin)->postJson("/api/agency/clients/{$client->id}/secondary-logins", [
            'email' => 'secondary@example.com',
            'password' => 'Secret123',
        ]);
        $storeResponse->assertOk()->assertJsonPath('data.email', 'secondary@example.com');

        // No User row is created for the secondary email
        $this->assertDatabaseMissing('users', ['email' => 'secondary@example.com']);

        // A primary User is auto-created for the client, and the secondary login points to it
        $primaryUser = User::where('email', $client->email)->first();
        $this->assertNotNull($primaryUser);
        $this->assertDatabaseHas('client_secondary_logins', [
            'client_id' => $client->id,
            'user_id' => $primaryUser->id,
            'email' => 'secondary@example.com',
        ]);

        // Logging in with secondary credentials returns the primary user's id
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'secondary@example.com',
            'password' => 'Secret123',
        ]);
        $loginResponse->assertOk();
        $this->assertSame($primaryUser->id, $loginResponse->json('user.id'));
    }

    public function test_secondary_login_rejects_an_email_already_in_users_table(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        User::factory()->create(['agency_id' => $agency->id, 'email' => 'taken@example.com']);

        $this->actingAsAgency($admin)
            ->postJson("/api/agency/clients/{$client->id}/secondary-logins", [
                'email' => 'taken@example.com',
                'password' => 'Secret123',
            ])
            ->assertStatus(422);
    }

    public function test_secondary_login_rejects_an_email_already_used_by_another_secondary_login(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $primaryUser = $client->user;
        ClientSecondaryLogin::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'user_id' => $primaryUser->id,
            'email' => 'already@example.com',
            'password' => 'Secret123',
        ]);

        $this->actingAsAgency($admin)
            ->postJson("/api/agency/clients/{$client->id}/secondary-logins", [
                'email' => 'already@example.com',
                'password' => 'Secret123',
            ])
            ->assertStatus(422);
    }

    public function test_destroy_secondary_login_removes_the_credential_but_not_the_primary_user(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $primaryUser = $client->user;
        $secondaryLogin = ClientSecondaryLogin::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'user_id' => $primaryUser->id,
            'email' => 'secondary@example.com',
            'password' => 'Secret123',
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/clients/{$client->id}/secondary-logins/{$secondaryLogin->id}")
            ->assertOk();

        $this->assertDatabaseMissing('client_secondary_logins', ['id' => $secondaryLogin->id]);
        // Primary user account must remain intact
        $this->assertDatabaseHas('users', ['id' => $primaryUser->id]);
    }

    public function test_password_endpoints_are_scoped_to_the_authenticated_agency(): void
    {
        [, $admin] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other6.test', 'subdomain_prefix' => 'other6', 'email' => fake()->unique()->safeEmail()]);
        $foreignClient = Client::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/clients/{$foreignClient->id}/password")
            ->assertStatus(404);
    }

    public function test_candidate_password_can_be_set_and_secondary_login_stores_credential_without_new_user(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'last_name' => 'Lee', 'email' => 'jamie@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/candidates/{$candidate->id}/password", ['password' => 'Secret123'])
            ->assertOk();

        $primaryUser = User::where('email', $candidate->email)->first();
        $this->assertNotNull($primaryUser);
        $this->assertTrue($primaryUser->hasRole('candidate'));

        $this->actingAsAgency($admin)->postJson("/api/agency/candidates/{$candidate->id}/secondary-logins", [
            'email' => 'secondary-candidate@example.com',
            'password' => 'Secret123',
        ])->assertOk();

        // No User row for the secondary email
        $this->assertDatabaseMissing('users', ['email' => 'secondary-candidate@example.com']);
        $this->assertDatabaseHas('candidate_secondary_logins', [
            'candidate_id' => $candidate->id,
            'user_id' => $primaryUser->id,
            'email' => 'secondary-candidate@example.com',
        ]);

        // Secondary email logs in as the primary user
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'secondary-candidate@example.com',
            'password' => 'Secret123',
        ]);
        $loginResponse->assertOk();
        $this->assertSame($primaryUser->id, $loginResponse->json('user.id'));
    }

    private function createAgencyScenario(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $admin = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        foreach (['agency_admin', 'client', 'candidate'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
            Role::findOrCreate($roleName, 'api');
        }
        $admin->assignRole('agency_admin');

        return [$agency, $admin];
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
