<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyCandidateProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_returns_basic_information_block_from_candidate_columns(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/profile");

        $response->assertOk()
            ->assertJsonPath('data.form_id', null)
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Jamie')
            ->assertJsonCount(1, 'data.blocks');

        // The password base field must never be exposed.
        $keys = collect($response->json('data.blocks.0.sections.0.fields'))->pluck('key');
        $this->assertNotContains('password', $keys);
    }

    public function test_show_returns_section_wise_registration_form_answers(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Candidate Registration',
            'slug' => 'candidate-registration',
            'entity' => 'candidate',
            'application_type' => 'registration',
            'user_type' => 'candidate',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Reference',
                    'sections' => [[
                        'name' => 'Reference Details',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Reference Name', 'name' => 'reference_name', 'is_required' => true],
                            ['type' => 'text_box', 'label' => 'CPR Certified', 'name' => 'cpr'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['reference_name' => 'John Doe', 'cpr' => 'Yes'],
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/profile");

        $response->assertOk()
            ->assertJsonPath('data.form_id', $form->id)
            ->assertJsonPath('data.form_name', 'Candidate Registration')
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.1.name', 'Reference')
            ->assertJsonPath('data.blocks.1.sections.0.name', 'Reference Details')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.key', 'reference_name')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.label', 'Reference Name')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.value', 'John Doe')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.is_required', true)
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.key', 'cpr')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.value', 'Yes');
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
