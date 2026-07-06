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

class ClientInterviewsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_client_interviews_list_returns_screen_ready_rows(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $upcomingInterview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled',
            interviewType: 'google_meet',
            interviewLink: 'https://meet.google.com/demo-link'
        );

        $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'Morning Babysitter',
            scheduledDate: '2026-01-08',
            status: 'completed'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/interviews?view=list&period=upcoming&filter[search]=After');

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'list')
            ->assertJsonPath('data.next_interview.id', $upcomingInterview->id)
            ->assertJsonPath('data.interviews.data.0.id', $upcomingInterview->id)
            ->assertJsonPath('data.interviews.data.0.title', 'After School Nanny')
            ->assertJsonPath('data.interviews.data.0.candidate.name', 'Darlene Robertson')
            ->assertJsonPath('data.interviews.data.0.date', '2026-01-18')
            ->assertJsonPath('data.interviews.data.0.time.range', '10:00 AM - 11:00 AM')
            ->assertJsonPath('data.interviews.data.0.period', 'upcoming')
            ->assertJsonPath('data.interviews.data.0.meeting.type', 'google_meet')
            ->assertJsonPath('data.interviews.data.0.meeting.can_join', true)
            ->assertJsonPath('data.interviews.data.0.actions.can_reschedule', true)
            ->assertJsonPath('data.interviews.data.0.actions.can_cancel', true)
            ->assertJsonPath('data.interviews.data.0.actions.change_deadline_hours', 4);
    }

    public function test_client_interviews_list_searches_by_candidate_name(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/interviews?view=list&search=Darlene');

        $response
            ->assertOk()
            ->assertJsonPath('data.interviews.data.0.title', 'After School Nanny');

        $emptyResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/interviews?view=list&search=Nonexistent');

        $emptyResponse
            ->assertOk()
            ->assertJsonCount(0, 'data.interviews.data');
    }

    public function test_client_interviews_calendar_returns_flat_events_for_month(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $januaryInterview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'Newborn Caregiver',
            scheduledDate: '2026-01-21',
            status: 'scheduled'
        );

        $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'February Interview',
            scheduledDate: '2026-02-02',
            status: 'scheduled'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/interviews?view=calendar&month=1&year=2026');

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'calendar')
            ->assertJsonPath('data.month', 1)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.filters.period', 'all')
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', "interview_{$januaryInterview->id}_2026-01-21")
            ->assertJsonPath('data.events.0.interview_id', $januaryInterview->id)
            ->assertJsonPath('data.events.0.job_type', 'long_term')
            ->assertJsonPath('data.events.0.job_type_label', 'Long-term')
            ->assertJsonPath('data.events.0.title', 'Newborn Caregiver')
            ->assertJsonPath('data.events.0.candidate.name', 'Darlene Robertson')
            ->assertJsonPath('data.events.0.date', '2026-01-21')
            ->assertJsonPath('data.events.0.date_label', 'Jan 21, 2026')
            ->assertJsonPath('data.events.0.location.label', '26 Berkshire Ave., Atlantic City, NJ, USA')
            ->assertJsonPath('data.events.0.location.city', 'Atlantic City')
            ->assertJsonPath('data.events.0.compensation.amount', '35.00')
            ->assertJsonPath('data.events.0.compensation.label', '$35/hr')
            ->assertJsonPath('data.events.0.status', 'scheduled')
            ->assertJsonPath('data.events.0.status_label', 'Scheduled')
            ->assertJsonMissingPath('data.events.0.job')
            ->assertJsonMissingPath('data.events.0.attendance')
            ->assertJsonPath('data.events.0.actions.view_details_url', "/api/client/interviews/{$januaryInterview->id}")
            ->assertJsonPath('data.events.0.actions.reschedule_url', "/api/client/interviews/{$januaryInterview->id}/reschedule")
            ->assertJsonPath('data.events.0.modal.title', 'Newborn Caregiver')
            ->assertJsonPath('data.events.0.modal.subtitle', 'Darlene Robertson')
            ->assertJsonPath('data.events.0.modal.date', '21 Jan, Wed')
            ->assertJsonPath('data.events.0.modal.time_range', '10:00 AM - 11:00 AM')
            ->assertJsonMissingPath('data.interviews');
    }

    public function test_client_can_view_owned_interview_details(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'Mothers Helper',
            scheduledDate: '2026-01-28',
            status: 'scheduled',
            interviewType: 'zoom',
            interviewLink: 'https://zoom.us/j/demo-link'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/interviews/{$interview->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $interview->id)
            ->assertJsonPath('data.title', 'Mothers Helper')
            ->assertJsonPath('data.candidate.email', 'darlene@example.com')
            ->assertJsonPath('data.job.city', 'Atlantic City')
            ->assertJsonPath('data.meeting.link', 'https://zoom.us/j/demo-link');
    }

    public function test_client_cannot_view_another_clients_interview(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $otherClient = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Other',
            'last_name' => 'Client',
            'email' => 'other-client@example.com',
        ]);

        $interview = $this->createInterview(
            agency: $agency,
            client: $otherClient,
            candidate: $candidate,
            title: 'Weekend Nanny',
            scheduledDate: '2026-01-19',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/interviews/{$interview->id}")
            ->assertNotFound();

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/client/interviews/{$interview->id}")
            ->assertNotFound();
    }

    public function test_client_can_reschedule_interview_before_deadline(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/client/interviews/{$interview->id}/reschedule", [
                'scheduled_date' => '2026-01-22',
                'available_from' => '14:00',
                'available_to' => '15:00',
                'reason' => 'A family emergency came up on the original date.',
            ]);

        // A reschedule is only a request: the confirmed meeting is untouched and
        // the proposed slot is surfaced under reschedule_request until the agency
        // approves it.
        $response
            ->assertOk()
            ->assertJsonPath('message', 'Reschedule request sent to the agency.')
            ->assertJsonPath('data.date', '2026-01-18')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.pending_reschedule', true)
            ->assertJsonPath('data.reschedule_request.date', '2026-01-22')
            ->assertJsonPath('data.reschedule_request.to', '3:00 PM')
            ->assertJsonPath('data.reschedule_request.reason', 'A family emergency came up on the original date.');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'id' => $interview->id,
            'scheduled_date' => '2026-01-18 00:00:00',
            'reschedule_date' => '2026-01-22 00:00:00',
            'reschedule_reason' => 'A family emergency came up on the original date.',
        ]);
    }

    public function test_reschedule_requires_a_reason(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/client/interviews/{$interview->id}/reschedule", [
                'scheduled_date' => '2026-01-22',
                'available_from' => '14:00',
                'available_to' => '15:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed');
    }

    public function test_client_cannot_reschedule_within_four_hours_of_start(): void
    {
        Carbon::setTestNow('2026-01-18 07:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/client/interviews/{$interview->id}/reschedule", [
                'scheduled_date' => '2026-01-22',
                'available_from' => '14:00',
                'available_to' => '15:00',
                'reason' => 'Trying to move it too late.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Interviews can only be rescheduled at least 4 hours before the scheduled time.');
    }

    public function test_client_can_cancel_interview_before_deadline(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/client/interviews/{$interview->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Interview cancelled successfully.');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'id' => $interview->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_client_cannot_cancel_within_four_hours_of_start(): void
    {
        Carbon::setTestNow('2026-01-18 07:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'scheduled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/client/interviews/{$interview->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Interviews can only be cancelled at least 4 hours before the scheduled time.');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'id' => $interview->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_cancelled_interview_cannot_be_rescheduled(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createClientScenario();

        $interview = $this->createInterview(
            agency: $agency,
            client: $client,
            candidate: $candidate,
            title: 'After School Nanny',
            scheduledDate: '2026-01-18',
            status: 'cancelled'
        );

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/client/interviews/{$interview->id}/reschedule", [
                'scheduled_date' => '2026-01-22',
                'available_from' => '14:00',
                'available_to' => '15:00',
                'reason' => 'Changed my mind.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only scheduled interviews can be rescheduled.');
    }

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
            'first_name' => 'Charlotte',
            'last_name' => 'Hamlin',
            'email' => $user->email,
        ]);

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Darlene',
            'last_name' => 'Robertson',
            'email' => 'darlene@example.com',
            'mobile' => '+14842918863',
        ]);

        return [$agency, $user, $client, $candidate];
    }

    private function createInterview(
        Agency $agency,
        Client $client,
        Candidate $candidate,
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
