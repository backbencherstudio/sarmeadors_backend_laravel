<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_dashboard_empty_state_returns_zero_stats_and_no_current_job(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.client.name', 'Alex Morrison')
            ->assertJsonPath('data.client.image_url', null)
            ->assertJsonPath('data.stats.total_job_posts', 0)
            ->assertJsonPath('data.stats.applications', 0)
            ->assertJsonPath('data.stats.messages', 0)
            ->assertJsonPath('data.stats.interviews', 0)
            ->assertJsonPath('data.current_job', null);
    }

    public function test_client_dashboard_returns_screen_ready_current_job_and_recommendations(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $applicant = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Emily',
            'last_name' => 'Stone',
            'email' => 'emily@example.com',
            'city' => 'Miami',
            'province' => 'New York',
            'country' => 'USA',
            'years_of_experience' => '5-10',
        ]);

        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Darlene Robertson',
            'description' => 'Full-time nanny needed for two children.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Miami',
            'home_province' => 'New York',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-01',
            'compensation_amount' => 34,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'marketplace',
        ]);

        LongTermJobApplication::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $applicant->id,
            'agency_id' => $agency->id,
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.total_job_posts', 1)
            ->assertJsonPath('data.stats.applications', 1)
            ->assertJsonPath('data.current_job.id', $job->id)
            ->assertJsonPath('data.current_job.job_type', 'long_term')
            ->assertJsonPath('data.current_job.title', 'Darlene Robertson')
            ->assertJsonPath('data.current_job.status_label', 'Pending')
            ->assertJsonPath('data.current_job.compensation.label', '$34/hr')
            ->assertJsonPath('data.current_job.applicants.count', 1)
            ->assertJsonPath('data.current_job.actions.can_view_details', true);

        $this->assertNotEmpty($response->json('data.recommended_candidates'));
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
