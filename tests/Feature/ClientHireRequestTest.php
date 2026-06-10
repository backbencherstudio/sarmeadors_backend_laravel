<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateJobRequest;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientHireRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_hire_request_creates_job_and_pending_request(): void
    {
        [$agency, $clientUser, $client, , $candidate] = $this->createScenario();

        $response = $this
            ->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", $this->hirePayload());

        $response
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'short_term')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.candidate.id', $candidate->id)
            ->assertJsonPath('data.job.title', 'After School Nanny');

        $this->assertDatabaseHas('candidate_job_requests', [
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'job_type' => 'short_term',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('short_term_jobs', [
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'After School Nanny',
            'status' => 'pending_approval',
        ]);

        $this->assertDatabaseHas('short_term_job_dates', [
            'booking_date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    public function test_candidate_sees_and_approves_the_hire_request(): void
    {
        [$agency, $clientUser, $client, $candidateUser, $candidate] = $this->createScenario();

        $this
            ->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", $this->hirePayload())
            ->assertCreated();

        $jobRequest = CandidateJobRequest::where('candidate_id', $candidate->id)->firstOrFail();

        // Candidate sees the pending request
        $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/requested')
            ->assertOk()
            ->assertJsonPath('data.requests.data.0.id', $jobRequest->id)
            ->assertJsonPath('data.requests.data.0.title', 'After School Nanny');

        // Candidate approves
        $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/candidate/jobs/requested/{$jobRequest->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('candidate_job_requests', [
            'id' => $jobRequest->id,
            'status' => 'approved',
        ]);

        // Job stays assigned to the candidate after approval
        $this->assertSame($candidate->id, ShortTermJob::find($jobRequest->short_term_job_id)->candidate_id);
    }

    public function test_candidate_reject_releases_the_job(): void
    {
        [$agency, $clientUser, $client, $candidateUser, $candidate] = $this->createScenario();

        $this
            ->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", $this->hirePayload())
            ->assertCreated();

        $jobRequest = CandidateJobRequest::where('candidate_id', $candidate->id)->firstOrFail();

        $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/candidate/jobs/requested/{$jobRequest->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('candidate_job_requests', [
            'id' => $jobRequest->id,
            'status' => 'rejected',
        ]);

        $this->assertNull(ShortTermJob::find($jobRequest->short_term_job_id)->candidate_id);
    }

    public function test_long_term_hire_request_is_routed_to_interview(): void
    {
        [$agency, $clientUser, $client, , $candidate] = $this->createScenario();

        $this
            ->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", ['job_type' => 'long-term'])
            ->assertStatus(422);
    }

    public function test_short_term_hire_request_requires_job_details(): void
    {
        [$agency, $clientUser, $client, , $candidate] = $this->createScenario();

        $this
            ->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", ['job_type' => 'short-term'])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['title', 'compensation_amount', 'job_address', 'dates']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function hirePayload(): array
    {
        return [
            'job_type' => 'short-term',
            'title' => 'After School Nanny',
            'description' => 'Pickup and after-school care.',
            'compensation_amount' => 35,
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'dates' => [
                ['booking_date' => '2026-07-01', 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
            'note' => 'Would love to work with you again.',
        ];
    }

    /**
     * @return array{0: Agency, 1: User, 2: Client, 3: User, 4: Candidate}
     */
    private function createScenario(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        Role::create(['name' => 'client', 'guard_name' => 'web']);
        Role::create(['name' => 'candidate', 'guard_name' => 'web']);

        $clientUser = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);
        $clientUser->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $clientUser->email,
        ]);

        $candidateUser = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);
        $candidateUser->assignRole('candidate');

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kelsey',
            'last_name' => 'Brooks',
            'email' => $candidateUser->email,
        ]);

        return [$agency, $clientUser, $client, $candidateUser, $candidate];
    }
}
