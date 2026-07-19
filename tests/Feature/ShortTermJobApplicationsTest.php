<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobDate;
use App\Models\ShortTermJobReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShortTermJobApplicationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_candidate_apply_creates_application_and_client_sees_counts_and_times(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $clientUser, $client] = $this->createClientScenario();
        [$candidateUserOne, $candidateOne] = $this->createCandidate($agency, 'Emily', 'Stone');
        [$candidateUserTwo, $candidateTwo] = $this->createCandidate($agency, 'Darlene', 'Robertson');

        $job = $this->createMarketplaceJob($agency, $client);

        ShortTermJobDate::create([
            'short_term_job_id' => $job->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        // Both candidates apply from the marketplace
        $this->actingAs($candidateUserOne, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/jobs/short-term/{$job->id}/apply", [
                'application_message' => 'I am available for this booking.',
            ])
            ->assertCreated();

        $this->actingAs($candidateUserTwo, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/jobs/short-term/{$job->id}/apply")
            ->assertCreated();

        // Applying does not claim the job anymore
        $this->assertNull($job->fresh()->candidate_id);

        // Duplicate application is rejected
        $this->actingAs($candidateUserOne, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/jobs/short-term/{$job->id}/apply")
            ->assertUnprocessable();

        // Client job list card shows applicant count and booking times
        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term?status=marketplace')
            ->assertOk()
            ->assertJsonPath('data.jobs.0.applicants.count', 2)
            ->assertJsonPath('data.jobs.0.applicants.interviewed', 0)
            ->assertJsonPath('data.jobs.0.schedule_summary.date', '2026-01-18')
            ->assertJsonPath('data.jobs.0.schedule_summary.date_label', '18 Jan, Sun')
            ->assertJsonPath('data.jobs.0.schedule_summary.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.jobs.0.booking_dates.0.start_time', '10:00 AM')
            ->assertJsonPath('data.jobs.0.booking_dates.0.end_time', '11:00 AM');

        // Job details carry the same applicant summary and times
        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/jobs/short-term/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.applications_count', 2)
            ->assertJsonPath('data.applicants.count', 2)
            ->assertJsonPath('data.schedule_summary.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.booking_dates.0.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonMissingPath('data.applications');

        // Client applicants endpoint lists both applications
        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/jobs/short-term/{$job->id}/applicants")
            ->assertOk()
            ->assertJsonPath('data.applications.total', 2)
            ->assertJsonPath('data.hired_candidate', null);

        // Hiring an applicant assigns them, starts the job, and settles all
        // application statuses
        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/jobs/short-term/{$job->id}/applicants/{$candidateOne->id}/hire")
            ->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.hired_candidate.id', $candidateOne->id)
            ->assertJsonPath('data.hired_candidate.name', 'Emily Stone');

        $job->refresh();
        $this->assertSame($candidateOne->id, $job->candidate_id);
        $this->assertSame('running', $job->status);

        // Job details now present the assigned candidate as a structured block
        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/jobs/short-term/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.candidate.header.id', $candidateOne->id)
            ->assertJsonPath('data.candidate.header.name', 'Emily Stone')
            ->assertJsonPath('data.candidate.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.candidate.blocks.0.sections.0.fields.0.value', 'Emily')
            ->assertJsonMissingPath('data.candidate.user_id')
            ->assertJsonPath('data.assigned_candidate.id', $candidateOne->id);

        // Only one candidate can be hired per job
        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/jobs/short-term/{$job->id}/applicants/{$candidateTwo->id}/hire")
            ->assertUnprocessable();

        $this->assertDatabaseHas('short_term_job_applications', [
            'short_term_job_id' => $job->id,
            'candidate_id' => $candidateOne->id,
            'status' => 'hired',
        ]);

        $this->assertDatabaseHas('short_term_job_applications', [
            'short_term_job_id' => $job->id,
            'candidate_id' => $candidateTwo->id,
            'status' => 'rejected',
        ]);
    }

    public function test_applied_job_leaves_marketplace_and_shows_in_candidate_applications(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $clientUser, $client] = $this->createClientScenario();
        [$candidateUser, $candidate] = $this->createCandidate($agency, 'Emily', 'Stone');

        $job = $this->createMarketplaceJob($agency, $client);

        $this->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/jobs/short-term/{$job->id}/apply")
            ->assertCreated();

        // The job disappears from the candidate's marketplace feed
        $this->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/short-term-marketplace')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        // ...and shows up in their applied jobs with the application meta
        $this->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/short-term-applications')
            ->assertOk()
            ->assertJsonPath('data.jobs.data.0.id', $job->id)
            ->assertJsonPath('data.jobs.data.0.has_applied', true)
            ->assertJsonPath('data.jobs.data.0.application.type', 'short_term_application')
            ->assertJsonPath('data.jobs.data.0.application.status', 'pending')
            ->assertJsonPath('data.jobs.data.0.application.status_label', 'Pending');

        // Withdrawing removes the application and restores the marketplace feed
        $this->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/candidate/jobs/short-term/{$job->id}/apply")
            ->assertOk();

        $this->assertDatabaseMissing('short_term_job_applications', [
            'short_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
        ]);

        $this->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/short-term-marketplace')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_client_can_view_short_term_applicant_detail(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $clientUser, $client] = $this->createClientScenario();
        [$candidateUser, $candidate] = $this->createCandidate($agency, 'Emily', 'Stone');

        $job = $this->createMarketplaceJob($agency, $client);

        $this->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/jobs/short-term/{$job->id}/apply", [
                'application_message' => 'I am available for this booking.',
            ])
            ->assertCreated();

        // A short-term review from this client must surface in the reviews block
        // and drive the header rating (short-term context, not long-term).
        ShortTermJobReview::create([
            'short_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'rating' => 4,
            'review' => 'Great with the kids.',
        ]);

        $this->actingAs($clientUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/jobs/short-term/{$job->id}/applicants/{$candidate->id}")
            ->assertOk()
            ->assertJsonPath('data.candidate.header.id', $candidate->id)
            ->assertJsonPath('data.candidate.header.name', 'Emily Stone')
            ->assertJsonPath('data.candidate.header.rating.count', 1)
            ->assertJsonPath('data.reviews.count', 1)
            ->assertJsonPath('data.reviews.items.0.rating', 4)
            ->assertJsonPath('data.reviews.my_review.rating', 4)
            ->assertJsonPath('data.candidate.blocks.0.sections.0.fields.0.value', 'Emily')
            ->assertJsonStructure([
                'data' => [
                    'candidate' => [
                        'header',
                        'form_id',
                        'form_name',
                        'blocks',
                        'documents',
                    ],
                    'reviews' => ['average', 'count', 'my_review', 'items'],
                    'application' => ['id', 'status', 'message', 'applied_at', 'job' => ['id', 'title'], 'interview'],
                    'link',
                    'actions' => ['can_review', 'can_hire_request'],
                ],
            ])
            ->assertJsonPath('data.application.status', 'pending')
            ->assertJsonPath('data.application.message', 'I am available for this booking.')
            ->assertJsonPath('data.application.job.id', $job->id)
            ->assertJsonPath('data.application.interview', null)
            ->assertJsonPath('data.actions.can_hire_request', true)
            ->assertJsonPath('data.actions.can_review', false)
            ->assertJsonPath('data.link', null);
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

        Role::create(['name' => 'client', 'guard_name' => 'web']);
        Role::create(['name' => 'candidate', 'guard_name' => 'web']);

        $user->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
        ]);

        return [$agency, $user, $client];
    }

    /**
     * @return array{0: User, 1: Candidate}
     */
    private function createCandidate(Agency $agency, string $firstName, string $lastName): array
    {
        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user->assignRole('candidate');

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
        ]);

        return [$user, $candidate];
    }

    private function createMarketplaceJob(Agency $agency, Client $client): ShortTermJob
    {
        return ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'After School Nanny',
            'description' => 'Full responsibility for three energetic children.',
            'job_address' => '71 Raglan Street',
            'home_city' => 'Crownthorpe',
            'home_province' => 'QLD',
            'home_postal_code' => '4605',
            'country' => 'Australia',
            'compensation_amount' => 34,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'marketplace',
        ]);
    }
}
