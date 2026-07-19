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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'image' => 'candidates/avatar.jpg',
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/profile");

        $response->assertOk()
            ->assertJsonPath('data.form_id', null)
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Jamie')
            ->assertJsonCount(1, 'data.blocks');

        // The image field returns the full public URL, not the raw storage path.
        $fields = collect($response->json('data.blocks.0.sections.0.fields'))->keyBy('key');
        $this->assertSame($candidate->image_url, $fields['image']['value']);
        $this->assertStringContainsString('candidates/avatar.jpg', $fields['image']['value']);

        // The password base field must never be exposed.
        $this->assertNotContains('password', $fields->keys());
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

    public function test_show_filters_to_a_single_block_when_a_slug_is_given(): void
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
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['reference_name' => 'John Doe'],
        ]);

        $response = $this->actingAsAgency($admin)
            ->getJson("/api/agency/candidates/{$candidate->id}/profile?slug=reference");

        $response->assertOk()
            ->assertJsonCount(1, 'data.blocks')
            ->assertJsonPath('data.blocks.0.name', 'Reference')
            ->assertJsonPath('data.blocks.0.slug', 'reference');
    }

    public function test_show_returns_an_empty_blocks_list_for_an_unknown_slug(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'email' => 'jamie@example.com',
        ]);

        $response = $this->actingAsAgency($admin)
            ->getJson("/api/agency/candidates/{$candidate->id}/profile?slug=does-not-exist");

        $response->assertOk()->assertJsonCount(0, 'data.blocks');
    }

    public function test_show_returns_the_full_option_list_alongside_a_choice_fields_answer(): void
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
                    'name' => 'Preferences',
                    'sections' => [[
                        'name' => 'Preference Details',
                        'fields' => [
                            [
                                'type' => 'radio',
                                'label' => 'Years of Experience',
                                'name' => 'years_of_experience',
                                'options' => ['2-5 years', '5-10 years', '10 plus years'],
                            ],
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
            'data' => ['years_of_experience' => '5-10 years', 'cpr' => 'Yes'],
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/profile");

        $fields = collect($response->json('data.blocks.1.sections.0.fields'))->keyBy('key');

        $response->assertOk();
        $this->assertSame('5-10 years', $fields['years_of_experience']['value']);
        $this->assertSame(['2-5 years', '5-10 years', '10 plus years'], $fields['years_of_experience']['options']);
        $this->assertNull($fields['cpr']['options']);
    }

    public function test_show_returns_full_urls_for_file_upload_and_list_files_answers(): void
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
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity & Certification Documents',
                        'fields' => [
                            ['type' => 'file_upload', 'label' => 'Resume', 'name' => 'resume'],
                            ['type' => 'list_files', 'label' => 'Certificates', 'name' => 'certificates'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => [
                'resume' => 'form-submissions/candidate/resume.pdf',
                'certificates' => ['form-submissions/candidate/cert1.pdf', 'form-submissions/candidate/cert2.pdf'],
            ],
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/profile");

        $fields = collect($response->json('data.blocks.1.sections.0.fields'))->keyBy('key');

        $response->assertOk();
        $this->assertSame(asset('storage/form-submissions/candidate/resume.pdf'), $fields['resume']['value']);
        $this->assertSame([
            asset('storage/form-submissions/candidate/cert1.pdf'),
            asset('storage/form-submissions/candidate/cert2.pdf'),
        ], $fields['certificates']['value']);
    }

    public function test_update_changes_basic_information_fields(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $type = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'candidate']);
        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);
        $linkedUser = User::where('agency_id', $agency->id)->where('email', 'jamie@example.com')->firstOrFail();

        $this->actingAsAgency($admin)->patchJson("/api/agency/candidates/{$candidate->id}/profile", [
            'first_name' => 'Kristin',
            'last_name' => 'Ben',
            'type_id' => [$type->id],
            'location_id' => [$location->id],
        ])->assertOk();

        $candidate->refresh();
        $this->assertSame('Kristin', $candidate->first_name);
        $this->assertSame('Ben', $candidate->last_name);
        $this->assertSame([$type->id], $candidate->type_id);
        $this->assertSame([$location->id], $candidate->location_id);
        $this->assertSame('Kristin', $linkedUser->fresh()->first_name);
    }

    public function test_update_profile_picture_can_be_changed(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        // Real clients must POST (not PATCH) when uploading a file: PHP only
        // parses multipart/form-data into $_FILES for POST requests, so a
        // genuine PATCH silently drops the file even though this test's HTTP
        // client wouldn't catch that (it injects files without going through
        // PHP's request parsing).
        $this->actingAsAgency($admin)
            ->post("/api/agency/candidates/{$candidate->id}/profile", ['image' => $file], ['Content-Type' => 'multipart/form-data'])
            ->assertOk();

        $path = $candidate->fresh()->image;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_update_can_upload_a_dynamic_document_field(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

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
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity & Certification Documents',
                        'fields' => [
                            ['type' => 'file_upload', 'label' => 'Resume', 'name' => 'resume'],
                        ],
                    ]],
                ]],
            ],
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['resume' => 'form-submissions/candidate/old-resume.pdf'],
        ]);
        Storage::disk('public')->put('form-submissions/candidate/old-resume.pdf', 'old contents');

        $file = UploadedFile::fake()->create('resume.pdf', 128, 'application/pdf');

        // Same PATCH-drops-files caveat as the profile-picture test above.
        $this->actingAsAgency($admin)
            ->post("/api/agency/candidates/{$candidate->id}/profile", ['resume' => $file], ['Content-Type' => 'multipart/form-data'])
            ->assertOk();

        $submission->refresh();
        $this->assertNotNull($submission->data['resume']);
        $this->assertNotSame('form-submissions/candidate/old-resume.pdf', $submission->data['resume']);
        Storage::disk('public')->assertExists($submission->data['resume']);
        Storage::disk('public')->assertMissing('form-submissions/candidate/old-resume.pdf');
    }

    public function test_admin_can_delete_a_candidates_uploaded_document(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

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
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity & Certification Documents',
                        'fields' => [
                            ['type' => 'file_upload', 'label' => 'NID', 'name' => 'nid'],
                        ],
                    ]],
                ]],
            ],
        ]);

        Storage::disk('public')->put('form-submissions/candidate/nid.png', 'contents');

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['nid' => 'form-submissions/candidate/nid.png'],
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/candidates/{$candidate->id}/profile/documents/nid")
            ->assertOk();

        $submission->refresh();
        $this->assertNull($submission->data['nid']);
        Storage::disk('public')->assertMissing('form-submissions/candidate/nid.png');
    }

    public function test_deleting_a_document_returns_404_for_a_field_that_is_not_a_document(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

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
                            ['type' => 'text_box', 'label' => 'Position', 'name' => 'position'],
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

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/candidates/{$candidate->id}/profile/documents/position")
            ->assertNotFound();
    }

    public function test_deleting_a_document_removes_only_the_requested_file_from_a_list_files_field(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

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
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity & Certification Documents',
                        'fields' => [
                            ['type' => 'list_files', 'label' => 'Certificates', 'name' => 'certificates'],
                        ],
                    ]],
                ]],
            ],
        ]);

        Storage::disk('public')->put('form-submissions/candidate/cert1.pdf', 'contents');
        Storage::disk('public')->put('form-submissions/candidate/cert2.pdf', 'contents');

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $candidate->id,
            'entity_type' => 'candidate',
            'data' => ['certificates' => ['form-submissions/candidate/cert1.pdf', 'form-submissions/candidate/cert2.pdf']],
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/candidates/{$candidate->id}/profile/documents/certificates", [
                'path' => 'form-submissions/candidate/cert1.pdf',
            ])
            ->assertOk();

        $submission->refresh();
        $this->assertSame(['form-submissions/candidate/cert2.pdf'], $submission->data['certificates']);
        Storage::disk('public')->assertMissing('form-submissions/candidate/cert1.pdf');
        Storage::disk('public')->assertExists('form-submissions/candidate/cert2.pdf');
    }

    public function test_update_writes_dynamic_answers_into_the_registration_submission(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

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

        $this->actingAsAgency($admin)->patchJson("/api/agency/candidates/{$candidate->id}/profile", [
            'hours_per_week' => '40',
            'position' => 'Housekeeper',
        ])->assertOk();

        $submission->refresh();
        $this->assertSame('40', $submission->data['hours_per_week']);
        $this->assertSame('Housekeeper', $submission->data['position']);
        $this->assertSame('40', $candidate->fresh()->hours_per_week);
    }

    public function test_update_rejects_blanking_a_required_dynamic_field(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

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

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/candidates/{$candidate->id}/profile", ['position' => null])
            ->assertStatus(422);
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
