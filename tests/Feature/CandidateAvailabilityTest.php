<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_can_view_default_availability_screen_data(): void
    {
        [, $user] = $this->createCandidateScenario();

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/availability');

        $response
            ->assertOk()
            ->assertJsonPath('data.availability.timezone', 'UTC')
            ->assertJsonPath('data.availability.days.0.day_name', 'Sunday')
            ->assertJsonPath('data.availability.days.0.is_available', false)
            ->assertJsonPath('data.availability.days.6.day_name', 'Saturday')
            ->assertJsonPath('data.unavailabilities', []);
    }

    public function test_candidate_can_update_weekly_availability_and_create_temporary_unavailability(): void
    {
        [, $user] = $this->createCandidateScenario();

        $updateResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson('/api/candidate/availability', [
                'timezone' => 'America/New_York',
                'days' => [
                    [
                        'day' => 'monday',
                        'is_available' => true,
                        'from' => '09:00',
                        'to' => '17:00',
                    ],
                    [
                        'day' => 'friday',
                        'is_available' => false,
                        'from' => null,
                        'to' => null,
                    ],
                ],
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.availability.timezone', 'America/New_York')
            ->assertJsonPath('data.availability.days.1.day_name', 'Monday')
            ->assertJsonPath('data.availability.days.1.is_available', true)
            ->assertJsonPath('data.availability.days.1.start_time', '09:00')
            ->assertJsonPath('data.availability.days.5.day_name', 'Friday')
            ->assertJsonPath('data.availability.days.5.is_available', false);

        $createResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/candidate/availability/unavailabilities', [
                'title' => 'Sick Leave',
                'start_date' => '2026-08-16',
                'end_date' => '2026-08-18',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.title', 'Sick Leave')
            ->assertJsonPath('data.start_date', '2026-08-16')
            ->assertJsonPath('data.end_date', '2026-08-18');
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

        return [$agency, $user, $candidate];
    }
}
