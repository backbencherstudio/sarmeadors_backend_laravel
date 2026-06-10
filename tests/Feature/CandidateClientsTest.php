<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobReview;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateClientsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_clients_list_returns_table_ready_rows(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateClientScenario();

        ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'After School Nanny',
            'description' => 'Pickup and after-school care.',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'running',
        ]);

        $otherClient = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Devon',
            'last_name' => 'Lane',
            'email' => 'devon@example.com',
            'mobile' => '+16102458249',
        ]);

        ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $otherClient->id,
            'candidate_id' => $candidate->id,
            'title' => 'Night Nanny',
            'description' => 'Overnight newborn care.',
            'compensation_amount' => 40,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'completed',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/clients?filter[search]=Renee');

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Renee McCoy')
            ->assertJsonPath('data.data.0.email', 'renee@example.com')
            ->assertJsonPath('data.data.0.mobile', '+14842918863')
            ->assertJsonPath('data.data.0.job_type', 'Short-Term Jobs')
            ->assertJsonPath('data.data.0.job_status', 'running');
    }

    public function test_candidate_client_details_returns_unified_job_history(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateClientScenario();

        $job = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'After School Nanny',
            'description' => 'Pickup and after-school care.',
            'job_address' => '71 Raglan Street',
            'home_city' => 'Crownthorpe',
            'home_province' => 'Queensland',
            'home_postal_code' => '4605',
            'country' => 'Australia',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'completed',
        ]);

        ShortTermJobReview::create([
            'short_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'rating' => 5,
            'review' => 'Great family to work with.',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/clients/{$client->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.client.name', 'Renee McCoy')
            ->assertJsonPath('data.client.rating.average', 5)
            ->assertJsonPath('data.client.rating.count', 1)
            ->assertJsonPath('data.job_history.0.id', $job->id)
            ->assertJsonPath('data.job_history.0.job_type', 'short_term')
            ->assertJsonPath('data.job_history.0.job_type_label', 'Short-Term Jobs')
            ->assertJsonPath('data.job_history.0.status', 'completed')
            ->assertJsonPath('data.job_history.0.can_view_review', true)
            ->assertJsonPath('data.job_history.0.can_report_client', true)
            ->assertJsonMissingPath('data.short_term_jobs')
            ->assertJsonMissingPath('data.long_term_jobs');
    }

    private function createCandidateClientScenario(): array
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
            'name' => 'candidate',
            'guard_name' => 'web',
        ]);

        $user->assignRole('candidate');

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
        ]);

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Renee',
            'last_name' => 'McCoy',
            'email' => 'renee@example.com',
            'mobile' => '+14842918863',
        ]);

        return [$agency, $user, $candidate, $client];
    }
}
