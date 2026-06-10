<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobReview;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientCandidatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_candidates_tab_returns_resource_cards_with_roles(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $nannyType = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'service']);
        $managerType = Type::create(['agency_id' => $agency->id, 'name' => 'House Manager', 'type' => 'service']);

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kelsey',
            'last_name' => 'Brooks',
            'email' => 'kelsey@example.com',
            'city' => 'Miami',
            'province' => 'New York',
            'country' => 'USA',
            'years_of_experience' => '5-10',
            'type_id' => [$nannyType->id, $managerType->id],
        ]);

        $job = $this->createLongTermJob($agency, $client);

        LongTermJobApplication::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'agency_id' => $agency->id,
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/candidates?tab=new');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Kelsey Brooks')
            ->assertJsonPath('data.0.location', 'Miami, New York, USA')
            ->assertJsonPath('data.0.experience_years', '5-10')
            ->assertJsonPath('data.0.roles', ['Nanny', 'House Manager'])
            ->assertJsonPath('data.0.actions.can_view_profile', true);
    }

    public function test_candidate_detail_returns_profile_tabs_and_reviews(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kristin',
            'last_name' => 'Ben',
            'email' => 'kristin@example.com',
            'nationality' => 'American',
            'hours_per_week' => '40',
            'years_of_experience' => '10+',
        ]);

        $job = $this->createLongTermJob($agency, $client, $candidate, 'completed');

        LongTermJobReview::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'rating' => 5,
            'review' => 'Excellent with the kids.',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/candidates/{$candidate->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.candidate.header.name', 'Kristin Ben')
            ->assertJsonPath('data.candidate.header.rating.average', 5)
            ->assertJsonPath('data.candidate.header.rating.count', 1)
            ->assertJsonPath('data.candidate.personal_information.first_name', 'Kristin')
            ->assertJsonPath('data.candidate.professional_information.hours_per_week', '40')
            ->assertJsonPath('data.candidate.additional_information.years_of_experience', '10+')
            ->assertJsonPath('data.reviews.count', 1)
            ->assertJsonPath('data.reviews.my_review.rating', 5)
            ->assertJsonPath('data.actions.can_hire_request', true);
    }

    public function test_client_can_submit_candidate_review(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jana',
            'last_name' => 'Smith',
            'email' => 'jana@example.com',
        ]);

        $this->createLongTermJob($agency, $client, $candidate, 'completed');

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/reviews", [
                'rating' => 4,
                'review' => 'Reliable and punctual.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.review', 'Reliable and punctual.')
            ->assertJsonPath('data.reviewer.name', 'Alex Morrison');

        $this->assertDatabaseHas('long_term_job_reviews', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'rating' => 4,
        ]);
    }

    public function test_hire_request_validates_job_type(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Pat',
            'last_name' => 'Doe',
            'email' => 'pat@example.com',
        ]);

        // Invalid job type -> 422
        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", [
                'job_type' => 'weekly',
            ])
            ->assertStatus(422);

        // Long-term hires are routed through the interview flow first -> 422
        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/candidates/{$candidate->id}/hire-request", [
                'job_type' => 'long-term',
            ])
            ->assertStatus(422);
    }

    private function createLongTermJob(Agency $agency, Client $client, ?Candidate $candidate = null, string $status = 'marketplace'): LongTermJob
    {
        return LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate?->id,
            'title' => 'Full Time Nanny',
            'description' => 'Long-term nanny role.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Miami',
            'home_province' => 'New York',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-01',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => $status,
        ]);
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
