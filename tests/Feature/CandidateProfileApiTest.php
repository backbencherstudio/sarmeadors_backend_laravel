<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Location;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateProfileApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_returns_basic_information_block_from_candidate_columns(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();

        $response = $this->actingAsCandidate($user)->getJson('/api/candidate/profile');

        $response->assertOk()
            ->assertJsonPath('data.id', $candidate->id)
            ->assertJsonPath('data.form_id', null)
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Alex')
            ->assertJsonCount(1, 'data.blocks');

        $fields = collect($response->json('data.blocks.0.sections.0.fields'))->keyBy('key');
        $this->assertNotContains('password', $fields->keys());
    }

    public function test_show_returns_section_wise_registration_form_answers(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();

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

        $response = $this->actingAsCandidate($user)->getJson('/api/candidate/profile');

        $response->assertOk()
            ->assertJsonPath('data.form_id', $form->id)
            ->assertJsonPath('data.form_name', 'Candidate Registration')
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.1.name', 'Reference')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.key', 'reference_name')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.value', 'John Doe')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.key', 'cpr')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.value', 'Yes');
    }

    public function test_update_changes_basic_information_fields(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();
        $type = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'candidate']);
        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);

        $this->actingAsCandidate($user)->postJson('/api/candidate/profile', [
            'first_name' => 'Kristin',
            'last_name' => 'Ben',
            'mobile' => '+15551230000',
            'nationality' => 'Canadian',
            'type_id' => [$type->id],
            'location_id' => [$location->id],
        ])->assertOk();

        $candidate->refresh();
        $this->assertSame('Kristin', $candidate->first_name);
        $this->assertSame('Ben', $candidate->last_name);
        $this->assertSame('Canadian', $candidate->nationality);
        $this->assertSame([$type->id], $candidate->type_id);
        $this->assertSame([$location->id], $candidate->location_id);
        $this->assertSame('Kristin', $user->fresh()->first_name);
    }

    public function test_update_does_not_change_the_login_email(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();

        $this->actingAsCandidate($user)->postJson('/api/candidate/profile', [
            'first_name' => 'Kristin',
            'email' => 'someone-else@example.com',
        ])->assertOk();

        $this->assertSame('alex@example.com', $candidate->fresh()->email);
        $this->assertSame('alex@example.com', $user->fresh()->email);
    }

    public function test_update_writes_dynamic_answers_into_the_registration_submission(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();

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
                    'name' => 'Professional Information',
                    'sections' => [[
                        'name' => 'Professional Information',
                        'fields' => [
                            // Reuses a real `candidates` column name, so it should also land on the model.
                            ['type' => 'text_box', 'label' => 'Hours per Week', 'name' => 'hours_per_week'],
                            // A purely agency-defined field with no matching column.
                            ['type' => 'text_box', 'label' => 'Position', 'name' => 'position', 'is_required' => true],
                        ],
                    ]],
                ]],
            ],
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['hours_per_week' => '30', 'position' => 'Nanny'],
        ]);

        $response = $this->actingAsCandidate($user)->postJson('/api/candidate/profile', [
            'hours_per_week' => '40',
            'position' => 'Housekeeper',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.key', 'position')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.value', 'Housekeeper');

        $submission->refresh();
        $this->assertSame('40', $submission->data['hours_per_week']);
        $this->assertSame('Housekeeper', $submission->data['position']);
        $this->assertSame('40', $candidate->fresh()->hours_per_week);
    }

    public function test_update_rejects_blanking_a_required_dynamic_field(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();

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
                    'name' => 'Professional Information',
                    'sections' => [[
                        'name' => 'Professional Information',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Position', 'name' => 'position', 'is_required' => true],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['position' => 'Nanny'],
        ]);

        $this->actingAsCandidate($user)
            ->postJson('/api/candidate/profile', ['position' => null])
            ->assertStatus(422);
    }

    public function test_profile_is_scoped_to_the_authenticated_candidate(): void
    {
        [$agency, $user, $candidate] = $this->createCandidateScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other.test', 'subdomain_prefix' => 'other', 'email' => fake()->unique()->safeEmail()]);
        Candidate::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsCandidate($user)
            ->getJson('/api/candidate/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $candidate->id);
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
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'mobile' => '+14842918883',
        ]);

        Role::findOrCreate('candidate', 'api');
        $user->assignRole(Role::where('name', 'candidate')->where('guard_name', 'api')->first());

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
            'mobile' => '+14842918883',
        ]);

        return [$agency, $user, $candidate];
    }

    private function actingAsCandidate(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
