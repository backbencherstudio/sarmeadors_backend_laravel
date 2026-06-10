<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
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

class CandidateJobBoardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_short_term_job_board_returns_card_and_detail_payloads(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $job = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Part Time Housekeeper',
            'description' => 'Temporary babysitting and light household support.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'compensation_amount' => 34,
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
            'allergies' => 'Mild allergy to peanuts.',
        ]);

        ShortTermJobDate::create([
            'short_term_job_id' => $job->id,
            'booking_date' => '2026-12-20',
            'start_time' => '08:45',
            'end_time' => '18:00',
        ]);

        $listResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/short-term-marketplace?filter[search]=Atlantic');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $job->id)
            ->assertJsonPath('data.data.0.job_type', 'short_term')
            ->assertJsonPath('data.data.0.title', 'Part Time Housekeeper')
            ->assertJsonPath('data.data.0.services.0', 'Nanny')
            ->assertJsonPath('data.data.0.location.city', 'Atlantic City')
            ->assertJsonPath('data.data.0.compensation.label', '$34/hr')
            ->assertJsonPath('data.data.0.can_apply', true);

        $detailResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/short-term-marketplace/{$job->id}");

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.client.name', 'Darlene Robertson')
            ->assertJsonPath('data.children.0.name', 'Savannah Nguyen')
            ->assertJsonPath('data.booking_dates.0.time_range', '8:45 AM - 6:00 PM')
            ->assertJsonPath('data.address.street_address', '26 Berkshire Ave.')
            ->assertJsonPath('data.budget.label', '$34/hr');
    }

    public function test_candidate_long_term_job_board_returns_card_and_detail_payloads(): void
    {
        [$agency, $user, $candidate, $client] = $this->createCandidateScenario();

        $job = LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Full Time Nanny in River North',
            'description' => 'Long-term nanny role with school pickup and activities.',
            'job_address' => '71 Raglan Street',
            'home_city' => 'Miami',
            'home_province' => 'FL',
            'home_postal_code' => '33101',
            'country' => 'USA',
            'start_date' => '2026-02-01',
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
            'start_time' => '08:45',
            'end_time' => '18:00',
        ]);

        $listResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/jobs/long-term-marketplace?filter[search]=Miami');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $job->id)
            ->assertJsonPath('data.data.0.job_type', 'long_term')
            ->assertJsonPath('data.data.0.services.1', 'House Manager')
            ->assertJsonPath('data.data.0.location.city', 'Miami')
            ->assertJsonPath('data.data.0.can_apply', true);

        $detailResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/long-term-marketplace/{$job->id}");

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.children.0.name', 'Courtney Henry')
            ->assertJsonPath('data.schedule.0.day_name', 'Monday')
            ->assertJsonPath('data.schedule.0.time_range', '8:45 AM - 6:00 PM')
            ->assertJsonPath('data.address.city', 'Miami')
            ->assertJsonPath('data.budget.label', '$35/hr');
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
            'email' => 'alex@example.com',
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
