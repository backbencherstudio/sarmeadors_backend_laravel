<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateProfileApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_profile_returns_data_sections_without_static_ui_fields(): void
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
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'mobile' => '+14842918883',
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
            'mobile' => '+14842918883',
            'date_of_birth' => '2025-12-12',
            'nationality' => 'American',
            'street_address' => '26 Berkshire Ave.',
            'city' => 'Atlantic City',
            'province' => 'NJ',
            'postal_code' => '08401',
            'country' => 'USA',
            'hours_per_week' => '40',
            'bilingual' => 'Spanish',
            'pay_range_per_hour' => '$35/hr',
            'start_date' => '2026-01-15',
            'reference_first_name' => 'Kristin',
            'reference_last_name' => 'Ben',
            'reference_phone' => '+14842918883',
            'reference_email' => 'reference@example.com',
            'reference_relation' => 'Former employer',
            'reference_description' => 'Worked together for two years.',
            'years_of_experience' => '5-10',
            'commitment' => 'long_term',
            'available_for' => ['full_time', 'part_time'],
            'drivers_license' => 'dl_and_car',
            'cpr_first_aid' => 'yes',
            'vaccinations' => 'yes',
            'work_legally_in_us' => true,
        ]);

        CandidateDocument::create([
            'agency_id' => $agency->id,
            'candidate_id' => $candidate->id,
            'required_key' => 'headshot',
            'category' => 'required',
            'title' => 'Please upload a headshot of yourself',
            'status' => 'uploaded',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/profile');

        $response
            ->assertOk()
            ->assertJsonPath('data.profile.header.name', 'Alex Morrison')
            ->assertJsonMissingPath('data.profile.header.can_share_profile')
            ->assertJsonMissingPath('data.profile.tabs')
            ->assertJsonPath('data.profile.personal_information.address.city', 'Atlantic City')
            ->assertJsonPath('data.profile.professional_information.bilingual', 'Spanish')
            ->assertJsonPath('data.profile.reference.first_name', 'Kristin')
            ->assertJsonPath('data.profile.additional_information.available_for.0', 'full_time')
            ->assertJsonPath('data.profile.documents_summary.required_uploaded', 1)
            ->assertJsonPath('data.user.email', 'alex@example.com');
    }
}
