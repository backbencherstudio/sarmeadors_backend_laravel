<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_dashboard_returns_screen_ready_job_feeds(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => 'agency@example.com',
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
            'first_name' => 'Renee',
            'last_name' => 'McCoy',
            'email' => 'renee@example.com',
        ]);

        $runningJob = ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'title' => 'After School Nanny',
            'description' => 'Pickup and after-school care.',
            'job_address' => '27 Queens Street',
            'home_city' => 'Mitchell',
            'home_province' => 'Queensland',
            'home_postal_code' => '4465',
            'country' => 'Australia',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'running',
        ]);

        ShortTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Part Time Housekeeper',
            'description' => 'Light household help.',
            'job_address' => '33 George Street',
            'home_city' => 'Mitchell',
            'home_province' => 'Queensland',
            'home_postal_code' => '4465',
            'country' => 'Australia',
            'compensation_amount' => 34,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => 'marketplace',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.short_term_jobs', 1)
            ->assertJsonPath('data.stats.my_jobs', 1)
            ->assertJsonPath('data.stats.my_families', 1)
            ->assertJsonPath('data.candidate.name', 'Alex Morrison')
            ->assertJsonPath('data.candidate.image_url', null)
            ->assertJsonPath('data.running_jobs.0.id', $runningJob->id)
            ->assertJsonPath('data.running_jobs.0.job_type', 'short_term')
            ->assertJsonPath('data.running_jobs.0.client_name', 'Renee McCoy')
            ->assertJsonPath('data.available_jobs.0.title', 'Part Time Housekeeper')
            ->assertJsonPath('data.available_jobs.0.job_type', 'short_term')
            ->assertJsonMissingPath('data.hero')
            ->assertJsonMissingPath('data.running_short_term_job')
            ->assertJsonMissingPath('data.running_long_term_job')
            ->assertJsonMissingPath('data.available_short_term_jobs')
            ->assertJsonMissingPath('data.available_long_term_jobs');
    }
}
