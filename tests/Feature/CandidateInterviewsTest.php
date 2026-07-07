<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateInterviewsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_candidate_interviews_list_returns_screen_ready_rows(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $upcomingInterview = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled',
            interviewType: 'google_meet',
            interviewLink: 'https://meet.google.com/demo-link'
        );

        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Morning Babysitter',
            scheduledDate: '2026-01-08',
            status: 'completed'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/interviews?view=list&status=scheduled&filter[search]=After');

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'list')
            ->assertJsonPath('data.next_interview.id', $upcomingInterview->id)
            ->assertJsonPath('data.interviews.data.0.id', $upcomingInterview->id)
            ->assertJsonPath('data.interviews.data.0.title', 'After School Nanny')
            ->assertJsonPath('data.interviews.data.0.client.name', 'Charlotte Hamlin')
            ->assertJsonPath('data.interviews.data.0.date', '2026-01-18')
            ->assertJsonPath('data.interviews.data.0.time.range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.interviews.data.0.period', 'scheduled')
            ->assertJsonPath('data.interviews.data.0.meeting.type', 'google_meet')
            ->assertJsonPath('data.interviews.data.0.meeting.can_join', true);
    }

    public function test_candidate_interviews_calendar_groups_by_date(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Newborn Caregiver',
            scheduledDate: '2026-01-21',
            status: 'scheduled'
        );

        // A second interview on the same day must land in the same date bucket
        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Afternoon Family Meeting',
            scheduledDate: '2026-01-21',
            status: 'scheduled'
        );

        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'February Interview',
            scheduledDate: '2026-02-02',
            status: 'scheduled'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/interviews?view=calendar&month=1&year=2026');

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'calendar')
            ->assertJsonPath('data.month', 1)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.filters.status', 'all')
            ->assertJsonCount(2, 'data.interviews')
            ->assertJsonPath('data.interviews.0.title', 'Newborn Caregiver')
            ->assertJsonPath('data.interviews.1.title', 'Afternoon Family Meeting')
            ->assertJsonPath('data.interviews.0.job_type', 'long_term')
            ->assertJsonPath('data.interviews.0.status_label', 'Scheduled')
            ->assertJsonPath('data.interviews.0.client.name', 'Charlotte Hamlin')
            ->assertJsonPath('data.interviews.0.date', '2026-01-21')
            ->assertJsonPath('data.interviews.0.time.range', '10:00 AM - 11:00 AM');
    }

    public function test_candidate_can_view_owned_interview_details(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $interview = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Mothers Helper',
            scheduledDate: '2026-01-28',
            status: 'scheduled',
            interviewType: 'zoom',
            interviewLink: 'https://zoom.us/j/demo-link'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/interviews/{$interview->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $interview->id)
            ->assertJsonPath('data.title', 'Mothers Helper')
            ->assertJsonPath('data.client.email', 'charlotte@example.com')
            ->assertJsonPath('data.job.city', 'Atlantic City')
            ->assertJsonPath('data.meeting.link', 'https://zoom.us/j/demo-link');
    }

    public function test_candidate_joining_meeting_auto_completes_interview_and_returns_link(): void
    {
        Carbon::setTestNow('2026-01-18 10:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $interview = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled',
            interviewType: 'zoom',
            interviewLink: 'https://zoom.us/j/demo-link'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/interviews/{$interview->id}/join");

        $response
            ->assertOk()
            ->assertJsonPath('data.meeting_link', 'https://zoom.us/j/demo-link')
            ->assertJsonPath('data.interview.status', 'completed')
            ->assertJsonPath('data.interview.period', 'completed');

        $this->assertSame('completed', $interview->fresh()->status);
    }

    public function test_candidate_cannot_join_interview_without_a_meeting_link(): void
    {
        Carbon::setTestNow('2026-01-18 10:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $interview = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'In Person Meeting',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/interviews/{$interview->id}/join")
            ->assertStatus(422);

        $this->assertSame('scheduled', $interview->fresh()->status);
    }

    public function test_candidate_cannot_join_an_interview_that_is_not_scheduled(): void
    {
        Carbon::setTestNow('2026-01-18 10:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $interview = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Already Completed',
            scheduledDate: '2026-01-10',
            status: 'completed',
            interviewType: 'zoom',
            interviewLink: 'https://zoom.us/j/demo-link'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/interviews/{$interview->id}/join")
            ->assertStatus(422);
    }

    public function test_candidate_missed_period_returns_past_scheduled_interviews(): void
    {
        Carbon::setTestNow('2026-01-20 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $missed = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Missed Meeting',
            scheduledDate: '2026-01-15',
            status: 'scheduled'
        );

        // A completed interview must not show under "missed".
        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Completed Meeting',
            scheduledDate: '2026-01-14',
            status: 'completed'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/interviews?view=list&status=missed')
            ->assertOk()
            ->assertJsonCount(1, 'data.interviews.data')
            ->assertJsonPath('data.interviews.data.0.id', $missed->id)
            ->assertJsonPath('data.interviews.data.0.period', 'missed');
    }

    public function test_candidate_completed_period_returns_only_completed_interviews(): void
    {
        Carbon::setTestNow('2026-01-20 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $completed = $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Completed Meeting',
            scheduledDate: '2026-01-14',
            status: 'completed'
        );

        // A past scheduled (missed) interview must not show under "completed".
        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Missed Meeting',
            scheduledDate: '2026-01-15',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/interviews?view=list&status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data.interviews.data')
            ->assertJsonPath('data.interviews.data.0.id', $completed->id)
            ->assertJsonPath('data.interviews.data.0.period', 'completed');
    }

    public function test_candidate_does_not_see_requests_still_awaiting_the_agency(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $this->createInterview(
            agency: $agency,
            candidate: $candidate,
            client: $client,
            title: 'Awaiting Agency',
            scheduledDate: '2026-01-18',
            status: 'requested'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/interviews?view=list')
            ->assertOk()
            ->assertJsonCount(0, 'data.interviews.data');
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
            'first_name' => 'Charlotte',
            'last_name' => 'Hamlin',
            'email' => 'charlotte@example.com',
            'mobile' => '+14842918863',
        ]);

        return [$agency, $user, $candidate, $client];
    }

    private function createInterview(
        Agency $agency,
        Candidate $candidate,
        Client $client,
        string $title,
        string $scheduledDate,
        string $status,
        string $interviewType = 'in_person',
        ?string $interviewLink = null
    ): LongTermJobInterview {
        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => $title,
            'description' => 'Full responsibility for three energetic children, including activities and routines.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-15',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'marketplace',
        ]);

        $application = LongTermJobApplication::create([
            'agency_id' => $agency->id,
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'interviewed',
        ]);

        return LongTermJobInterview::create([
            'agency_id' => $agency->id,
            'long_term_job_id' => $job->id,
            'long_term_job_application_id' => $application->id,
            'candidate_id' => $candidate->id,
            'scheduled_date' => $scheduledDate,
            'available_from' => '10:00',
            'available_to' => '11:00',
            'interview_type' => $interviewType,
            'interview_link' => $interviewLink,
            'description' => 'Discuss responsibilities, schedule, and household expectations.',
            'status' => $status,
        ]);
    }
}
