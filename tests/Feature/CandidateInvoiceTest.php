<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use App\Models\LongTermJobAttendance;
use App\Models\LongTermJobNannyPayment;
use App\Models\ShortTermJob;
use App\Models\ShortTermJobAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateInvoiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_short_term_invoice_returns_line_items_and_totals(): void
    {
        [$agency, $user, $candidate, $client] = $this->createScenario();

        $job = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'After School Nanny',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'running',
        ]);

        ShortTermJobAttendance::create([
            'short_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'booking_date' => '2026-01-18',
            'check_in' => '10:00',
            'check_out' => '12:00',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/short-term/{$job->id}/invoice");

        $response
            ->assertOk()
            ->assertJsonPath('data.job_type', 'short_term')
            ->assertJsonPath('data.candidate.name', 'Alex Morrison')
            ->assertJsonPath('data.client.name', 'Arlene McCoy')
            ->assertJsonPath('data.compensation.label', '$35 per hour')
            ->assertJsonPath('data.line_items.0.date', '2026-01-18')
            ->assertJsonPath('data.line_items.0.worked_minutes', 120)
            ->assertJsonPath('data.line_items.0.amount', 70)
            ->assertJsonPath('data.totals.total_earning', 70)
            ->assertJsonPath('data.totals.total_payment', 0)
            ->assertJsonPath('data.totals.due_payment', 70);
    }

    public function test_long_term_invoice_subtracts_recorded_payments_from_due(): void
    {
        [$agency, $user, $candidate, $client] = $this->createScenario();

        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'Morning Caregiver',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'start_date' => '2026-01-01',
            'status' => 'running',
        ]);

        LongTermJobAttendance::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'date' => '2026-01-18',
            'check_in' => '10:00',
            'check_out' => '12:00',
        ]);

        LongTermJobNannyPayment::create([
            'long_term_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'agency_id' => $agency->id,
            'amount' => 50,
            'currency' => 'usd',
            'payment_date' => '2026-01-19',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/long-term/{$job->id}/invoice");

        $response
            ->assertOk()
            ->assertJsonPath('data.job_type', 'long_term')
            ->assertJsonPath('data.line_items.0.amount', 70)
            ->assertJsonPath('data.totals.total_earning', 70)
            ->assertJsonPath('data.totals.total_payment', 50)
            ->assertJsonPath('data.totals.due_payment', 20);
    }

    public function test_invoice_rejects_jobs_belonging_to_another_candidate(): void
    {
        [$agency, $user, $candidate, $client] = $this->createScenario();

        $otherCandidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lane',
            'email' => 'jamie@example.com',
        ]);

        $job = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $otherCandidate->id,
            'title' => 'Not Yours',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'running',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/short-term/{$job->id}/invoice")
            ->assertNotFound();
    }

    private function createScenario(): array
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
