<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateJobRequest;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobChild;
use App\Models\LongTermJobSchedule;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateRequestedJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_can_list_view_and_approve_short_term_requested_job(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $job = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'After School Nanny',
            'description' => 'Full responsibility for three energetic children.',
            'job_address' => '71 Raglan Street',
            'home_city' => 'Crownthorpe',
            'home_province' => 'QLD',
            'home_postal_code' => '4605',
            'country' => 'Australia',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'marketplace',
        ]);

        ShortTermJobChild::create([
            'short_term_job_id' => $job->id,
            'first_name' => 'Savannah',
            'last_name' => 'Nguyen',
            'date_of_birth' => '2020-02-01',
            'gender' => 'female',
            'interests' => 'Drawing and scooter riding.',
        ]);

        ShortTermJobDate::create([
            'short_term_job_id' => $job->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $jobRequest = CandidateJobRequest::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'short_term_job_id' => $job->id,
            'job_type' => 'short_term',
            'status' => 'pending',
        ]);

        $listResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/requested?filter[job_type]=short_term&filter[search]=After');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.notice', 'These requests come from your previous Clients to work with them again.')
            ->assertJsonPath('data.requests.data.0.id', $jobRequest->id)
            ->assertJsonPath('data.requests.data.0.title', 'After School Nanny')
            ->assertJsonPath('data.requests.data.0.request.status_label', 'Pending')
            ->assertJsonPath('data.requests.data.0.actions.can_approve', true)
            ->assertJsonPath('data.requests.data.0.compensation.label', '$35/hr');

        $detailResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/requested/{$jobRequest->id}");

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.job.title', 'After School Nanny')
            ->assertJsonPath('data.children.0.name', 'Savannah Nguyen')
            ->assertJsonPath('data.booking_dates.0.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.actions.can_reject', true);

        $approveResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/candidate/jobs/requested/{$jobRequest->id}/approve");

        $approveResponse
            ->assertOk()
            ->assertJsonPath('data.request.status', 'approved')
            ->assertJsonPath('data.actions.can_approve', false);

        $this->assertSame('pending_approval', $job->fresh()->status);
        $this->assertSame('approved', $jobRequest->fresh()->status);
    }

    public function test_candidate_can_reject_long_term_requested_job(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Long Term Nanny',
            'description' => 'A recurring long-term nanny role.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-02-01',
            'compensation_amount' => 36,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'has_housekeeper' => true,
            'status' => 'marketplace',
        ]);

        LongTermJobChild::create([
            'long_term_job_id' => $job->id,
            'first_name' => 'Courtney',
            'last_name' => 'Henry',
            'date_of_birth' => '2020-02-01',
            'gender' => 'female',
        ]);

        LongTermJobSchedule::create([
            'long_term_job_id' => $job->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $application = LongTermJobApplication::create([
            'agency_id' => $agency->id,
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'hired',
        ]);

        $jobRequest = CandidateJobRequest::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'long_term_job_id' => $job->id,
            'long_term_job_application_id' => $application->id,
            'job_type' => 'long_term',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/candidate/jobs/requested/{$jobRequest->id}/reject");

        $response
            ->assertOk()
            ->assertJsonPath('data.request.status', 'rejected')
            ->assertJsonPath('data.schedule.0.time_range', '10:00 AM - 11:00 AM');

        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame('rejected', $jobRequest->fresh()->status);
    }

    private function createCandidateScenario(): array
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
            'first_name' => 'Arlene',
            'last_name' => 'McCoy',
            'email' => 'arlene@example.com',
        ]);

        return [$agency, $user, $candidate, $client];
    }
}
