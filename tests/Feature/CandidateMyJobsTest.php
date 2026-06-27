<?php

namespace Tests\Feature;

use App\Events\NewJobMessage;
use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobMessage;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use App\Models\LongTermJobChild;
use App\Models\LongTermJobNannyPayment;
use App\Models\LongTermJobReview;
use App\Models\LongTermJobSchedule;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use App\Models\ShortTermJobChild;
use App\Models\ShortTermJobDate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
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

        [
            $agency,
            $user,
            $candidate,
            $client,
        ] = $this->createCandidateScenario();

        $shortTermJob = $this->createShortTermJob(
            $agency,
            $candidate,
            $client,
            [
                'title' => 'After School Nanny',
                'status' => 'running',
            ],
        );

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

        $response = $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson(
                '/api/candidate/jobs?view=calendar&month=1&year=2026&filter[status]=running',
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'calendar')
            ->assertJsonPath(
                'data.events_by_date.2026-01-18.0.title',
                'After School Nanny',
            )
            ->assertJsonPath(
                'data.events_by_date.2026-01-18.0.modal.can_check_in',
                true,
            )
            ->assertJsonPath(
                'data.events_by_date.2026-01-18.0.time.range',
                '10:00 AM - 11:00 AM',
            )
            ->assertJsonPath(
                'data.events_by_date.2026-01-18.1.job_type',
                'long_term',
            );

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/short-term/{$shortTermJob->id}/check-in",
            )
            ->assertOk();

        $this->assertDatabaseHas('short_term_job_attendance', [
            'short_term_job_id' => $shortTermJob->id,
            'candidate_id' => $candidate->id,
        ]);
    }

    public function test_candidate_my_jobs_list_returns_running_completed_and_cancelled_screen_payloads(): void
    {
        Carbon::setTestNow('2026-01-18 09:00:00');

        [
            $agency,
            $user,
            $candidate,
            $client,
        ] = $this->createCandidateScenario();

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

        LongTermJobAttendance::create([
            'long_term_job_id' => $completedJob->id,
            'candidate_id' => $candidate->id,
            'date' => '2026-01-18',
            'check_in' => '10:00',
            'check_out' => '12:05',
        ]);

        LongTermJobReview::create([
            'long_term_job_id' => $completedJob->id,
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'agency_id' => $agency->id,
            'rating' => 5,
            'review' => 'Great family.',
        ]);

        $cancelledJob = $this->createShortTermJob(
            $agency,
            $candidate,
            $client,
            [
                'title' => 'Cancelled Nanny',
                'status' => 'cancelled',
                'cancellation_reason' => 'Family plans changed.',
                'cancelled_at' => '2026-01-17 10:00:00',
            ],
        );

        ShortTermJobDate::create([
            'short_term_job_id' => $cancelledJob->id,
            'booking_date' => '2026-01-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $runningResponse = $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson(
                '/api/candidate/jobs?view=list&filter[status]=running&filter[search]=Running',
            );

        $runningResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Running Job')
            ->assertJsonPath('data.jobs.0.id', $runningJob->id)
            ->assertJsonPath('data.jobs.0.title', 'Running Nanny')
            ->assertJsonPath('data.jobs.0.job_type', 'short_term')
            ->assertJsonPath('data.jobs.0.status', 'running')
            ->assertJsonPath('data.jobs.0.client_name', 'Arlene McCoy')
            ->assertJsonPath('data.jobs.0.address.city', 'Crownthorpe')
            ->assertJsonPath('data.jobs.0.compensation.type', 'per_hour')
            ->assertJsonPath('data.jobs.0.can_check_in', false)
            ->assertJsonPath('data.jobs.0.can_check_out', false)
            ->assertJsonMissingPath('data.jobs.0.actions')
            ->assertJsonMissingPath('data.jobs_by_date');

        $completedResponse = $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list&filter[status]=completed');

        $completedResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Completed Job')
            ->assertJsonPath('data.jobs.0.id', $completedJob->id)
            ->assertJsonPath('data.jobs.0.title', 'Completed Nanny')
            ->assertJsonPath('data.jobs.0.job_type', 'long_term')
            ->assertJsonPath('data.jobs.0.status', 'completed')
            ->assertJsonPath('data.jobs.0.can_check_in', false)
            ->assertJsonPath('data.jobs.0.can_check_out', false);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/long-term/{$completedJob->id}/client-report",
                [
                    'reason' => 'Client did not follow the agreed work terms.',
                ],
            )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('candidate_client_reports', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'long_term_job_id' => $completedJob->id,
            'job_type' => 'long_term',
            'status' => 'pending',
        ]);

        $cancelledResponse = $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list&filter[status]=cancelled');

        $cancelledResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Cancelled Job')
            ->assertJsonPath('data.jobs.0.id', $cancelledJob->id)
            ->assertJsonPath('data.jobs.0.title', 'Cancelled Nanny')
            ->assertJsonPath('data.jobs.0.status', 'cancelled')
            ->assertJsonPath('data.jobs.0.latest_attendance', null)
            ->assertJsonPath('data.jobs.0.can_check_in', false);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/short-term/{$cancelledJob->id}/client-report",
                [
                    'reason' => 'Client cancelled after I had reserved the time.',
                ],
            )
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('candidate_client_reports', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'short_term_job_id' => $cancelledJob->id,
            'job_type' => 'short_term',
            'status' => 'pending',
        ]);
    }

    public function test_candidate_my_jobs_list_filters_by_remaining_statuses_and_defaults_to_all_jobs(): void
    {
        [
            $agency,
            $user,
            $candidate,
            $client,
        ] = $this->createCandidateScenario();

        $rejectedShortTerm = $this->createShortTermJob(
            $agency,
            $candidate,
            $client,
            [
                'title' => 'Rejected Babysitting',
                'status' => 'rejected',
                'rejection_reason' => 'Agency could not verify the requested availability.',
            ],
        );

        ShortTermJobDate::create([
            'short_term_job_id' => $rejectedShortTerm->id,
            'booking_date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $pendingApprovalLongTerm = $this->createLongTermJob(
            $agency,
            $candidate,
            $client,
            [
                'title' => 'Pending Approval Nanny',
                'status' => 'pending_approval',
            ],
        );

        LongTermJobSchedule::create([
            'long_term_job_id' => $pendingApprovalLongTerm->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $marketplaceLongTerm = $this->createLongTermJob(
            $agency,
            $candidate,
            $client,
            [
                'title' => 'Marketplace Nanny',
                'status' => 'marketplace',
            ],
        );

        LongTermJobSchedule::create([
            'long_term_job_id' => $marketplaceLongTerm->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list&filter[status]=rejected')
            ->assertOk()
            ->assertJsonPath('data.title', 'Rejected Job')
            ->assertJsonPath('data.jobs.0.status', 'rejected')
            ->assertJsonPath('data.jobs.0.title', 'Rejected Babysitting')
            ->assertJsonPath('data.jobs.0.job_type', 'short_term');

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson(
                '/api/candidate/jobs?view=list&filter[status]=pending_approval',
            )
            ->assertOk()
            ->assertJsonPath('data.title', 'Pending Approval Job')
            ->assertJsonPath('data.jobs.0.status', 'pending_approval')
            ->assertJsonPath('data.jobs.0.title', 'Pending Approval Nanny')
            ->assertJsonPath('data.jobs.0.job_type', 'long_term');

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson(
                '/api/candidate/jobs?view=list&filter[status]=marketplace',
            )
            ->assertOk()
            ->assertJsonPath('data.title', 'Marketplace Job')
            ->assertJsonPath('data.jobs.0.status', 'marketplace')
            ->assertJsonPath('data.jobs.0.title', 'Marketplace Nanny')
            ->assertJsonPath('data.jobs.0.job_type', 'long_term');

        $allResponse = $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs?view=list');

        $allResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'All Jobs')
            ->assertJsonCount(3, 'data.jobs');
    }

    public function test_candidate_attendance_check_in_and_check_out_routes_validate_job_schedule(): void
    {
        Carbon::setTestNow('2026-01-18 09:00:00');

        [
            $agency,
            $user,
            $candidate,
            $client,
        ] = $this->createCandidateScenario();

        $shortTermJob = $this->createShortTermJob(
            $agency,
            $candidate,
            $client,
            [
                'status' => 'running',
            ],
        );

        ShortTermJobDate::create([
            'short_term_job_id' => $shortTermJob->id,
            'booking_date' => '2026-01-18',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/short-term/{$shortTermJob->id}/check-in",
            )
            ->assertOk()
            ->assertJsonPath('data.check_in', '09:00');

        Carbon::setTestNow('2026-01-18 10:15:00');

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/short-term/{$shortTermJob->id}/check-out",
            )
            ->assertOk()
            ->assertJsonPath('data.check_out', '10:15');

        $longTermJob = $this->createLongTermJob($agency, $candidate, $client, [
            'status' => 'running',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        LongTermJobSchedule::create([
            'long_term_job_id' => $longTermJob->id,
            'day_of_week' => 0,
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        Carbon::setTestNow('2026-01-18 12:05:00');

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/long-term/{$longTermJob->id}/check-in",
            )
            ->assertOk()
            ->assertJsonPath('data.check_in', '12:05');

        Carbon::setTestNow('2026-01-18 13:10:00');

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/long-term/{$longTermJob->id}/check-out",
            )
            ->assertOk()
            ->assertJsonPath('data.check_out', '13:10');

        $unscheduledJob = $this->createLongTermJob(
            $agency,
            $candidate,
            $client,
            [
                'status' => 'running',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ],
        );

        LongTermJobSchedule::create([
            'long_term_job_id' => $unscheduledJob->id,
            'day_of_week' => 1,
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/long-term/{$unscheduledJob->id}/check-in",
            )
            ->assertUnprocessable();

        $this->assertDatabaseMissing('long_term_job_attendance', [
            'long_term_job_id' => $unscheduledJob->id,
            'candidate_id' => $candidate->id,
        ]);
    }

    public function test_candidate_my_job_details_return_attendance_details_messages_and_broadcast_channels(): void
    {
        Carbon::setTestNow('2026-01-18 09:00:00');

        [
            $agency,
            $user,
            $candidate,
            $client,
        ] = $this->createCandidateScenario();

        $sender = User::factory()->create([
            'agency_id' => $agency->id,
            'first_name' => 'Davis',
            'last_name' => 'Rosser',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $shortTermJob = $this->createShortTermJob(
            $agency,
            $candidate,
            $client,
            [
                'title' => 'After School Nanny',
                'status' => 'running',
            ],
        );

        ShortTermJobDate::create([
            'short_term_job_id' => $shortTermJob->id,
            'booking_date' => '2026-01-18',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        JobMessage::create([
            'short_term_job_id' => $shortTermJob->id,
            'sender_id' => $sender->id,
            'thread' => 'candidate',
            'message' => 'Please confirm the schedule.',
        ]);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson(
                "/api/candidate/jobs/short-term/{$shortTermJob->id}?month=2026-01",
            )
            ->assertOk()
            ->assertJsonPath('data.job_type', 'short_term')
            ->assertJsonPath(
                'data.tabs.attendance_calendar.today.is_booked',
                true,
            )
            ->assertJsonPath(
                'data.tabs.job_details.booking_details.dates.0.date',
                '2026-01-18',
            )
            ->assertJsonPath('data.tabs.messages.total', 1)
            ->assertJsonPath('data.tabs.messages.unread', 1)
            ->assertJsonPath(
                'data.tabs.messages.channel',
                "private-short-term-job-messages.{$shortTermJob->id}",
            )
            ->assertJsonPath(
                'data.actions.messages_url',
                "/api/candidate/jobs/short-term/{$shortTermJob->id}/messages",
            );

        $longTermJob = $this->createLongTermJob($agency, $candidate, $client, [
            'title' => 'Long Term Nanny',
            'status' => 'running',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'family_schedule' => 'Structured weekly routine.',
        ]);

        LongTermJobSchedule::create([
            'long_term_job_id' => $longTermJob->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        LongTermJobAttendance::create([
            'long_term_job_id' => $longTermJob->id,
            'candidate_id' => $candidate->id,
            'date' => '2026-01-18',
            'check_in' => '10:00',
            'check_out' => '12:05',
        ]);

        LongTermJobNannyPayment::create([
            'long_term_job_id' => $longTermJob->id,
            'candidate_id' => $candidate->id,
            'agency_id' => $agency->id,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 50,
            'currency' => 'usd',
            'payment_method' => 'bank',
            'payment_date' => '2026-01-19',
        ]);

        JobMessage::create([
            'long_term_job_id' => $longTermJob->id,
            'sender_id' => $sender->id,
            'thread' => 'candidate',
            'message' => 'Long-term thread message.',
        ]);

        $response = $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson(
                "/api/candidate/jobs/long-term/{$longTermJob->id}?month=2026-01",
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.job_type', 'long_term')
            ->assertJsonPath(
                'data.tabs.attendance_calendar.summary.total_worked_minutes',
                125,
            )
            ->assertJsonPath(
                'data.tabs.attendance_calendar.summary.total_payment',
                50,
            )
            ->assertJsonPath(
                'data.tabs.attendance_calendar.today.is_scheduled',
                true,
            )
            ->assertJsonPath(
                'data.tabs.job_details.requirements.has_housekeeper',
                true,
            )
            ->assertJsonPath(
                'data.tabs.job_details.additional_information.family_schedule',
                'Structured weekly routine.',
            )
            ->assertJsonPath('data.tabs.messages.total', 1)
            ->assertJsonPath(
                'data.tabs.messages.channel',
                "private-job-messages.{$longTermJob->id}",
            )
            ->assertJsonPath(
                'data.actions.messages_url',
                "/api/candidate/jobs/long-term/{$longTermJob->id}/messages",
            );
    }

    public function test_candidate_job_messages_dispatch_pusher_broadcast_event(): void
    {
        [
            $agency,
            $user,
            $candidate,
            $client,
        ] = $this->createCandidateScenario();

        $shortTermJob = $this->createShortTermJob($agency, $candidate, $client);
        $longTermJob = $this->createLongTermJob($agency, $candidate, $client);

        Event::fake([NewJobMessage::class]);

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/short-term/{$shortTermJob->id}/messages",
                [
                    'message' => 'Short-term message from candidate.',
                ],
            )
            ->assertCreated();

        $this->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson(
                "/api/candidate/jobs/long-term/{$longTermJob->id}/messages",
                [
                    'message' => 'Long-term message from candidate.',
                ],
            )
            ->assertCreated();

        Event::assertDispatched(NewJobMessage::class, 2);

        $this->assertDatabaseHas('job_messages', [
            'short_term_job_id' => $shortTermJob->id,
            'sender_id' => $user->id,
            'thread' => 'candidate',
            'message' => 'Short-term message from candidate.',
        ]);

        $this->assertDatabaseHas('job_messages', [
            'long_term_job_id' => $longTermJob->id,
            'sender_id' => $user->id,
            'thread' => 'candidate',
            'message' => 'Long-term message from candidate.',
        ]);
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

    private function createShortTermJob(
        Agency $agency,
        Candidate $candidate,
        Client $client,
        array $overrides = [],
    ): ShortTermJob {
        $job = ShortTermJob::create(
            array_merge(
                [
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
                ],
                $overrides,
            ),
        );

        ShortTermJobChild::create([
            'short_term_job_id' => $job->id,
            'first_name' => 'Savannah',
            'last_name' => 'Nguyen',
            'date_of_birth' => '2020-02-01',
            'gender' => 'female',
        ]);

        return $job;
    }

    private function createLongTermJob(
        Agency $agency,
        Candidate $candidate,
        Client $client,
        array $overrides = [],
    ): LongTermJob {
        $job = LongTermJob::create(
            array_merge(
                [
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
                ],
                $overrides,
            ),
        );

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
