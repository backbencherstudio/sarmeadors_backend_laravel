<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientShortTermJobIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_status_filter_works_with_query_param(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->createJob($agency, $client, ['title' => 'Live job', 'status' => 'marketplace']);
        $this->createJob($agency, $client, ['title' => 'Pending job', 'status' => 'pending_approval']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term?status=marketplace')
            ->assertOk()
            ->assertJsonCount(1, 'data.jobs')
            ->assertJsonPath('data.jobs.0.title', 'Live job');
    }

    public function test_status_filter_works_with_native_filter_param(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->createJob($agency, $client, ['title' => 'Live job', 'status' => 'marketplace']);
        $this->createJob($agency, $client, ['title' => 'Pending job', 'status' => 'pending_approval']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term?filter[status]=pending_approval')
            ->assertOk()
            ->assertJsonCount(1, 'data.jobs')
            ->assertJsonPath('data.jobs.0.title', 'Pending job');
    }

    public function test_without_status_returns_all_jobs_with_counts(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->createJob($agency, $client, ['title' => 'Nanny', 'status' => 'marketplace']);
        $this->createJob($agency, $client, ['title' => 'Sitter', 'status' => 'pending_approval']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term')
            ->assertOk()
            ->assertJsonCount(2, 'data.jobs')
            ->assertJsonPath('data.counts.marketplace', 1)
            ->assertJsonPath('data.counts.pending_approval', 1);
    }

    private function createJob(Agency $agency, Client $client, array $overrides = []): ShortTermJob
    {
        return ShortTermJob::create(array_merge([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'After School Nanny',
            'description' => 'Childcare role.',
            'job_address' => '71 Raglan Street',
            'home_city' => 'Crownthorpe',
            'home_province' => 'QLD',
            'home_postal_code' => '4605',
            'country' => 'Australia',
            'compensation_amount' => 25,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'marketplace',
        ], $overrides));
    }

    /**
     * @return array{0: Agency, 1: User, 2: Client}
     */
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
        ]);

        Role::create([
            'name' => 'client',
            'guard_name' => 'web',
        ]);

        $user->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
        ]);

        return [$agency, $user, $client];
    }
}
