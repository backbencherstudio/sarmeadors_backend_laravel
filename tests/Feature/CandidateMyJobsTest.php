<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobChild;
use App\Models\LongTermJobReview;
use App\Models\LongTermJobSchedule;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateMyJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_candidate_my_jobs_calendar_returns_events_and_modal_payload(): void
    {
        Carbon::setTestNow('2026-01-18 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $shortTermJob = $this->createShortTermJob($agency, $candidate, $client, [
            'title' => 'After School Nanny',
            'status' => 'running',
        ]);

        ShortTermJobDate::create([
            'short_term_job_id' => $shortTermJob->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $longTermJob = $this->createLongTermJob($agency, $candidate, $client, [
            'title' => 'Morning Caregiver',
            'status' => 'running',
            'start_date' => '2026-01-01',
        ]);

        LongTermJobSchedule::create([
            'long_term_job_id' => $longTermJob->id,
            'day_of_week' => 0,
            'start_time' => '15:25',
            'end_time' => '16:25',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=calendar&month=1&year=2026&filter[status]=running');

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'calendar')
            ->assertJsonPath('data.events_by_date.2026-01-18.0.title', 'After School Nanny')
            ->assertJsonPath('data.events_by_date.2026-01-18.0.modal.can_check_in', true)
            ->assertJsonPath('data.events_by_date.2026-01-18.0.time.range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.events_by_date.2026-01-18.1.job_type', 'long_term');
    }

    public function test_candidate_my_jobs_list_returns_running_completed_and_cancelled_screen_payloads(): void
    {
        Carbon::setTestNow('2026-01-18 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $runningJob = $this->createShortTermJob($agency, $candidate, $client, [
            'title' => 'Running Nanny',
            'status' => 'running',
        ]);

        ShortTermJobDate::create([
            'short_term_job_id' => $runningJob->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        ShortTermJobAttendance::create([
            'short_term_job_id' => $runningJob->id,
            'candidate_id' => $candidate->id,
            'booking_date' => '2026-01-18',
            'check_in' => '08:52',
            'check_out' => '09:05',
        ]);

        $completedJob = $this->createLongTermJob($agency, $candidate, $client, [
            'title' => 'Completed Nanny',
            'status' => 'completed',
        ]);

        LongTermJobSchedule::create([
            'long_term_job_id' => $completedJob->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        LongTermJobReview::create([
            'long_term_job_id' => $completedJob->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'rating' => 5,
            'review' => 'Great family.',
        ]);

        $cancelledJob = $this->createShortTermJob($agency, $candidate, $client, [
            'title' => 'Cancelled Nanny',
            'status' => 'cancelled',
            'cancellation_reason' => 'Family plans changed.',
            'cancelled_at' => '2026-01-17 10:00:00',
        ]);

        ShortTermJobDate::create([
            'short_term_job_id' => $cancelledJob->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $runningResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list&filter[status]=running');

        $runningResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Running Job')
            ->assertJsonPath('data.jobs.0.title', 'Running Nanny')
            ->assertJsonPath('data.jobs.0.attendance.checked_in_at', '8:52 AM')
            ->assertJsonPath('data.jobs.0.attendance.checked_out_at', '9:05 AM')
            ->assertJsonPath('data.jobs.0.actions.can_check_in', false);

        $completedResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list&filter[status]=completed');

        $completedResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Completed Job')
            ->assertJsonPath('data.jobs.0.title', 'Completed Nanny')
            ->assertJsonPath('data.jobs.0.actions.can_view_review', true)
            ->assertJsonPath('data.jobs.0.actions.can_leave_review', false);

        $cancelledResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list&filter[status]=cancelled');

        $cancelledResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Cancelled Job')
            ->assertJsonPath('data.jobs.0.title', 'Cancelled Nanny')
            ->assertJsonPath('data.jobs.0.cancellation.reason', 'Family plans changed.')
            ->assertJsonPath('data.jobs.0.actions.can_report_client', true);
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

    private function createShortTermJob(Agency $agency, Candidate $candidate, Client $client, array $overrides = []): ShortTermJob
    {
        $job = ShortTermJob::create(array_merge([
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
            'status' => 'running',
        ], $overrides));

        ShortTermJobChild::create([
            'short_term_job_id' => $job->id,
            'first_name' => 'Savannah',
            'last_name' => 'Nguyen',
            'date_of_birth' => '2020-02-01',
            'gender' => 'female',
        ]);

        return $job;
    }

    private function createLongTermJob(Agency $agency, Candidate $candidate, Client $client, array $overrides = []): LongTermJob
    {
        $job = LongTermJob::create(array_merge([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'Long Term Nanny',
            'description' => 'A recurring long-term nanny role.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-01',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'has_housekeeper' => true,
            'status' => 'running',
        ], $overrides));

        LongTermJobChild::create([
            'long_term_job_id' => $job->id,
            'first_name' => 'Courtney',
            'last_name' => 'Henry',
            'date_of_birth' => '2020-02-01',
            'gender' => 'female',
        ]);

        return $job;
    }
}
