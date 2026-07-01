<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyCandidateProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_returns_all_profile_sections(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
            'mobile' => '+11234567890',
            'nationality' => 'American',
            'street_address' => '26 Berkshire Ave.',
            'city' => 'Atlantic City',
            'province' => 'NJ',
            'postal_code' => '08401',
            'country' => 'USA',
            'hours_per_week' => '40',
            'bilingual' => 'Spanish',
            'pay_range_per_hour' => '$20-$25',
            'last_position_end_reason' => 'Family relocated',
            'reference_first_name' => 'John',
            'reference_last_name' => 'Doe',
            'reference_phone' => '+10987654321',
            'reference_email' => 'john@example.com',
            'reference_relation' => 'Former employer',
            'reference_description' => 'Excellent caregiver',
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/profile");

        $response->assertOk()
            ->assertJsonPath('data.personal_information.first_name', 'Jamie')
            ->assertJsonPath('data.personal_information.last_name', 'Lee')
            ->assertJsonPath('data.personal_information.email', 'jamie@example.com')
            ->assertJsonPath('data.personal_information.phone_number', '+11234567890')
            ->assertJsonPath('data.personal_information.nationality', 'American')
            ->assertJsonPath('data.personal_information.address.street_address', '26 Berkshire Ave.')
            ->assertJsonPath('data.personal_information.address.city', 'Atlantic City')
            ->assertJsonPath('data.personal_information.address.province', 'NJ')
            ->assertJsonPath('data.personal_information.address.postal_code', '08401')
            ->assertJsonPath('data.personal_information.address.country', 'USA')
            ->assertJsonPath('data.professional_information.hours_per_week', '40')
            ->assertJsonPath('data.professional_information.bilingual', 'Spanish')
            ->assertJsonPath('data.professional_information.pay_range_per_hour', '$20-$25')
            ->assertJsonPath('data.professional_information.last_position_end_reason', 'Family relocated')
            ->assertJsonPath('data.reference.first_name', 'John')
            ->assertJsonPath('data.reference.last_name', 'Doe')
            ->assertJsonPath('data.reference.phone', '+10987654321')
            ->assertJsonPath('data.reference.email', 'john@example.com')
            ->assertJsonPath('data.reference.relation', 'Former employer')
            ->assertJsonPath('data.reference.description', 'Excellent caregiver')
            ->assertJsonStructure(['data' => ['personal_information', 'professional_information', 'reference', 'additional_information']]);
    }

    public function test_update_personal_information(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $this->actingAsAgency($admin)->patchJson("/api/agency/candidates/{$candidate->id}/profile", [
            'first_name' => 'Kristin',
            'last_name' => 'Ben',
            'phone_number' => '+14842918883',
            'nationality' => 'American',
            'street_address' => '26 Berkshire Ave.',
            'city' => 'Atlantic City',
            'province' => 'NJ',
            'postal_code' => '08401',
            'country' => 'USA',
        ])->assertOk();

        $candidate->refresh();
        $this->assertSame('Kristin', $candidate->first_name);
        $this->assertSame('Ben', $candidate->last_name);
        $this->assertSame('+14842918883', $candidate->mobile);
        $this->assertSame('Atlantic City', $candidate->city);
    }

    public function test_update_professional_information_and_reference(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $this->actingAsAgency($admin)->patchJson("/api/agency/candidates/{$candidate->id}/profile", [
            'hours_per_week' => '35',
            'bilingual' => 'French',
            'pay_range_per_hour' => '$18-$22',
            'last_position_end_reason' => 'Contract ended',
            'reference_first_name' => 'Jane',
            'reference_last_name' => 'Smith',
            'reference_phone' => '+15559876543',
            'reference_email' => 'jane@example.com',
            'reference_relation' => 'Supervisor',
            'reference_description' => 'Highly recommended',
        ])->assertOk();

        $candidate->refresh();
        $this->assertSame('35', $candidate->hours_per_week);
        $this->assertSame('French', $candidate->bilingual);
        $this->assertSame('Jane', $candidate->reference_first_name);
        $this->assertSame('jane@example.com', $candidate->reference_email);
    }

    public function test_update_additional_information(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $this->actingAsAgency($admin)->patchJson("/api/agency/candidates/{$candidate->id}/profile", [
            'years_of_experience' => '5-10',
            'commitment' => 'long_term',
            'drivers_license' => 'dl_and_car',
            'cpr_first_aid' => 'yes',
            'vaccinations' => 'willing',
            'ok_with_pets' => 'dog',
            'ok_with_travel' => 'domestic',
            'work_legally_in_us' => true,
            'comfortable_paid_legally' => true,
            'has_ssn' => true,
        ])->assertOk();

        $candidate->refresh();
        $this->assertSame('5-10', $candidate->years_of_experience);
        $this->assertSame('long_term', $candidate->commitment);
        $this->assertTrue($candidate->work_legally_in_us);
        $this->assertTrue($candidate->has_ssn);
    }

    public function test_update_rejects_duplicate_email(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);
        Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Other', 'email' => 'other@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/candidates/{$candidate->id}/profile", ['email' => 'other@example.com'])
            ->assertStatus(422);
    }

    public function test_update_accepts_same_email_for_same_candidate(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/candidates/{$candidate->id}/profile", ['email' => 'jamie@example.com'])
            ->assertOk();
    }

    public function test_profile_is_scoped_to_the_authenticated_agency(): void
    {
        [, $admin] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other.test', 'subdomain_prefix' => 'other', 'email' => fake()->unique()->safeEmail()]);
        $foreignCandidate = Candidate::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/candidates/{$foreignCandidate->id}/profile")
            ->assertStatus(404);
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

        $admin = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        foreach (['agency_admin', 'client', 'candidate'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
            Role::findOrCreate($roleName, 'api');
        }
        $admin->assignRole('agency_admin');

        return [$agency, $admin];
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
