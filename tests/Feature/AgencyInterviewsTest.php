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

class AgencyInterviewsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_filters_by_status_tab(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $requested = $this->createInterview($agency, $client, $candidate, 'Requested', '2026-01-20', 'requested');
        $this->createInterview($agency, $client, $candidate, 'Scheduled', '2026-01-21', 'scheduled');
        $this->createInterview($agency, $client, $candidate, 'Reschedule Pending', '2026-01-22', 'scheduled', rescheduleRequestedAt: '2026-01-09 12:00:00');

        $this->actingAsAgency($user)
            ->getJson('/api/agency/interviews?status=requested')
            ->assertOk()
            ->assertJsonCount(1, 'data.interviews.data')
            ->assertJsonPath('data.interviews.data.0.id', $requested->id);

        $this->actingAsAgency($user)
            ->getJson('/api/agency/interviews?status=scheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data.interviews.data')
            ->assertJsonPath('data.interviews.data.0.title', 'Scheduled');

        $this->actingAsAgency($user)
            ->getJson('/api/agency/interviews?status=rescheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data.interviews.data')
            ->assertJsonPath('data.interviews.data.0.pending_reschedule', true);
    }

    public function test_index_calendar_groups_by_date(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $this->createInterview($agency, $client, $candidate, 'Jan Interview', '2026-01-21', 'scheduled');

        $this->actingAsAgency($user)
            ->getJson('/api/agency/interviews?view=calendar&month=1&year=2026')
            ->assertOk()
            ->assertJsonPath('data.view', 'calendar')
            ->assertJsonPath('data.interviews.2026-01-21.0.title', 'Jan Interview');
    }

    public function test_agency_schedules_interview_from_scratch(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $this->actingAsAgency($user)
            ->postJson('/api/agency/interviews', [
                'title' => 'Intro Meeting',
                'candidate_ids' => [$candidate->id],
                'client_id' => $client->id,
                'scheduled_date' => '2026-01-20',
                'available_from' => '14:00',
                'available_to' => '15:00',
                'timezone' => 'America/New_York',
                'location' => '456 Birchwood Court',
                'interview_link' => 'https://zoom.us/j/scratch',
                'interview_type' => 'zoom',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Interview scheduled successfully.')
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.title', 'Intro Meeting')
            ->assertJsonPath('data.0.meeting.link', 'https://zoom.us/j/scratch');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'agency_id' => $agency->id,
            'candidate_id' => $candidate->id,
            'title' => 'Intro Meeting',
            'status' => 'scheduled',
            'long_term_job_id' => null,
        ]);
    }

    public function test_agency_creates_meeting_from_a_request(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview($agency, $client, $candidate, 'Requested', '2026-01-20', 'requested');

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/schedule", [
                'interview_link' => 'https://zoom.us/j/set',
                'interview_type' => 'zoom',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.meeting.link', 'https://zoom.us/j/set');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'id' => $interview->id,
            'status' => 'scheduled',
            'interview_link' => 'https://zoom.us/j/set',
        ]);
    }

    public function test_agency_approving_reschedule_uses_the_proposed_slot(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview(
            $agency,
            $client,
            $candidate,
            'Reschedule Pending',
            '2026-01-20',
            'scheduled',
            rescheduleRequestedAt: '2026-01-09 12:00:00',
            rescheduleDate: '2026-01-27',
            rescheduleFrom: '16:00',
            rescheduleTo: '17:00'
        );

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/schedule", [
                'interview_link' => 'https://zoom.us/j/re',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.pending_reschedule', false)
            ->assertJsonPath('data.date', '2026-01-27');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'id' => $interview->id,
            'scheduled_date' => '2026-01-27 00:00:00',
            'reschedule_requested_at' => null,
            'reschedule_date' => null,
        ]);
    }

    public function test_schedule_requires_a_link(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview($agency, $client, $candidate, 'Requested', '2026-01-20', 'requested');

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/schedule", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('data.interview_link.0', 'The interview link field is required.');
    }

    public function test_agency_declines_a_request_and_cancels_it(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview($agency, $client, $candidate, 'Requested', '2026-01-20', 'requested');

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/decline", [
                'reason' => 'No suitable candidate availability.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'No suitable candidate availability.');
    }

    public function test_agency_declining_a_reschedule_keeps_the_original_meeting(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview(
            $agency,
            $client,
            $candidate,
            'Reschedule Pending',
            '2026-01-20',
            'scheduled',
            rescheduleRequestedAt: '2026-01-09 12:00:00',
            rescheduleDate: '2026-01-27'
        );

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.pending_reschedule', false)
            ->assertJsonPath('data.date', '2026-01-20');

        $this->assertDatabaseHas('long_term_job_interviews', [
            'id' => $interview->id,
            'scheduled_date' => '2026-01-20 00:00:00',
            'reschedule_requested_at' => null,
        ]);
    }

    public function test_agency_cancels_a_scheduled_interview(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview($agency, $client, $candidate, 'Scheduled', '2026-01-20', 'scheduled');

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/cancel", [
                'reason' => 'Client withdrew.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Client withdrew.');
    }

    public function test_agency_completes_a_scheduled_interview(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [$agency, $user, $client, $candidate] = $this->createAgencyScenario();

        $interview = $this->createInterview($agency, $client, $candidate, 'Scheduled', '2026-01-20', 'scheduled');

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_agency_cannot_touch_another_agencys_interview(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        [, $user] = $this->createAgencyScenario();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $otherClient = Client::create([
            'agency_id' => $otherAgency->id,
            'first_name' => 'Other',
            'last_name' => 'Client',
            'email' => 'other-client@example.com',
        ]);
        $otherCandidate = Candidate::create([
            'agency_id' => $otherAgency->id,
            'first_name' => 'Other',
            'last_name' => 'Candidate',
            'email' => 'other-candidate@example.com',
        ]);

        $interview = $this->createInterview($otherAgency, $otherClient, $otherCandidate, 'Foreign', '2026-01-20', 'requested');

        $this->actingAsAgency($user)
            ->putJson("/api/agency/interviews/{$interview->id}/schedule", [
                'interview_link' => 'https://zoom.us/j/x',
            ])
            ->assertNotFound();
    }

    private function createAgencyScenario(): array
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

        Role::findOrCreate('agency_admin', 'web');
        $user->assignRole('agency_admin');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Charlotte',
            'last_name' => 'Hamlin',
            'email' => 'charlotte@example.com',
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

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }

    private function createInterview(
        Agency $agency,
        Client $client,
        Candidate $candidate,
        string $title,
        string $scheduledDate,
        string $status,
        ?string $rescheduleRequestedAt = null,
        ?string $rescheduleDate = null,
        ?string $rescheduleFrom = null,
        ?string $rescheduleTo = null
    ): LongTermJobInterview {
        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => $title,
            'description' => 'Full responsibility for three energetic children.',
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
            'interview_type' => 'in_person',
            'interview_link' => $status === 'scheduled' ? 'https://zoom.us/j/original' : null,
            'description' => 'Discuss responsibilities and expectations.',
            'reschedule_requested_at' => $rescheduleRequestedAt,
            'reschedule_reason' => $rescheduleRequestedAt ? 'A conflict came up.' : null,
            'reschedule_date' => $rescheduleDate,
            'reschedule_from' => $rescheduleFrom,
            'reschedule_to' => $rescheduleTo,
            'status' => $status,
        ]);
    }
}
