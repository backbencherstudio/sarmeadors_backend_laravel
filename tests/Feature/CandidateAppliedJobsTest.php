<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobChild;
use App\Models\LongTermJobInterview;
use App\Models\LongTermJobSchedule;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateAppliedJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_short_term_applied_jobs_match_applied_page_payload(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $job = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'Night Nanny Needed',
            'description' => 'Overnight support for a young family.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Miami',
            'home_province' => 'NY',
            'home_postal_code' => '10001',
            'country' => 'USA',
            'compensation_amount' => 34,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'pending_approval',
        ]);

        ShortTermJobChild::create([
            'short_term_job_id' => $job->id,
            'first_name' => 'Savannah',
            'last_name' => 'Nguyen',
            'date_of_birth' => '2020-02-01',
            'gender' => 'female',
        ]);

        ShortTermJobDate::create([
            'short_term_job_id' => $job->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $listResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/short-term-applications?filter[search]=Night');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.jobs.data.0.id', $job->id)
            ->assertJsonPath('data.jobs.data.0.title', 'Night Nanny Needed')
            ->assertJsonPath('data.jobs.data.0.application.status_label', 'Pending')
            ->assertJsonPath('data.jobs.data.0.actions.can_open_interview', false)
            ->assertJsonPath('data.jobs.data.0.compensation.label', '$34/hr');

        $detailResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/short-term-applications/{$job->id}");

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.children.0.name', 'Savannah Nguyen')
            ->assertJsonPath('data.booking_dates.0.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.application.status', 'pending_approval')
            ->assertJsonPath('data.interview', null);
    }

    public function test_candidate_long_term_applied_jobs_include_detail_and_interview_modal_payload(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'After School Nanny',
            'description' => 'Help with school pickup, activities, and evening routines.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-15',
            'compensation_amount' => 35,
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
            'application_message' => 'I am available for this role.',
            'status' => 'interviewed',
        ]);

        $interview = LongTermJobInterview::create([
            'agency_id' => $agency->id,
            'long_term_job_id' => $job->id,
            'long_term_job_application_id' => $application->id,
            'candidate_id' => $candidate->id,
            'scheduled_date' => '2026-01-18',
            'available_from' => '10:00',
            'available_to' => '11:00',
            'interview_type' => 'google_meet',
            'interview_link' => 'https://meet.google.com/demo-link',
            'description' => 'Review job expectations and schedule.',
            'status' => 'scheduled',
        ]);

        $listResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/long-term-applications?filter[search]=After');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.jobs.data.0.id', $job->id)
            ->assertJsonPath('data.jobs.data.0.application_id', $application->id)
            ->assertJsonPath('data.jobs.data.0.application.status_label', 'Interview')
            ->assertJsonPath('data.jobs.data.0.actions.can_open_interview', true)
            ->assertJsonPath('data.jobs.data.0.interview.id', $interview->id)
            ->assertJsonPath('data.jobs.data.0.interview.time.range', '10:00 AM - 11:00 AM');

        $detailResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/long-term-applications/{$application->id}");

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.children.0.name', 'Courtney Henry')
            ->assertJsonPath('data.schedule.0.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.interview.modal.title', 'After School Nanny')
            ->assertJsonPath('data.interview.modal.subtitle', 'You and Darlene Robertson')
            ->assertJsonPath('data.interview.meeting.can_join', true);
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
            'first_name' => 'Darlene',
            'last_name' => 'Robertson',
            'email' => 'darlene@example.com',
        ]);

        return [$agency, $user, $candidate, $client];
    }
}
