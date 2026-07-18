<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
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

class AgencyClientProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_returns_basic_information_block_from_client_columns(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
            'image' => 'clients/avatar.jpg',
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/clients/{$client->id}/profile");

        $response->assertOk()
            ->assertJsonPath('data.form_id', null)
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Jamie')
            ->assertJsonCount(1, 'data.blocks');

        // The image field returns the full public URL, not the raw storage path.
        $fields = collect($response->json('data.blocks.0.sections.0.fields'))->keyBy('key');
        $this->assertSame($client->image_url, $fields['image']['value']);
        $this->assertStringContainsString('clients/avatar.jpg', $fields['image']['value']);

        // The password base field must never be exposed.
        $this->assertNotContains('password', $fields->keys());
    }

    public function test_show_returns_section_wise_registration_form_answers(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Household',
                    'sections' => [[
                        'name' => 'Household Details',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Number of Children', 'name' => 'children_count', 'is_required' => true],
                            ['type' => 'text_box', 'label' => 'Care Type Needed', 'name' => 'care_type'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['children_count' => '2', 'care_type' => 'Full-Time'],
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/clients/{$client->id}/profile");

        $response->assertOk()
            ->assertJsonPath('data.form_id', $form->id)
            ->assertJsonPath('data.form_name', 'Client Registration')
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.1.name', 'Household')
            ->assertJsonPath('data.blocks.1.sections.0.name', 'Household Details')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.key', 'children_count')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.label', 'Number of Children')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.value', '2')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.is_required', true)
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.key', 'care_type')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.value', 'Full-Time');
    }

    public function test_show_returns_the_full_option_list_alongside_a_choice_fields_answer(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
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
                            ['type' => 'text_box', 'label' => 'Care Type Needed', 'name' => 'care_type'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['years_of_experience' => '5-10 years', 'care_type' => 'Full-Time'],
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/clients/{$client->id}/profile");

        $fields = collect($response->json('data.blocks.1.sections.0.fields'))->keyBy('key');

        $response->assertOk();
        $this->assertSame('5-10 years', $fields['years_of_experience']['value']);
        $this->assertSame(['2-5 years', '5-10 years', '10 plus years'], $fields['years_of_experience']['options']);
        $this->assertNull($fields['care_type']['options']);
    }

    public function test_show_returns_full_urls_for_file_upload_and_list_files_answers(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jamie',
            'last_name' => 'Lee',
            'email' => 'jamie@example.com',
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity Documents',
                        'fields' => [
                            ['type' => 'file_upload', 'label' => 'ID Proof', 'name' => 'id_proof'],
                            ['type' => 'list_files', 'label' => 'Certificates', 'name' => 'certificates'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => [
                'id_proof' => 'form-submissions/client/id-proof.pdf',
                'certificates' => ['form-submissions/client/cert1.pdf', 'form-submissions/client/cert2.pdf'],
            ],
        ]);

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/clients/{$client->id}/profile");

        $fields = collect($response->json('data.blocks.1.sections.0.fields'))->keyBy('key');

        $response->assertOk();
        $this->assertSame(asset('storage/form-submissions/client/id-proof.pdf'), $fields['id_proof']['value']);
        $this->assertSame([
            asset('storage/form-submissions/client/cert1.pdf'),
            asset('storage/form-submissions/client/cert2.pdf'),
        ], $fields['certificates']['value']);
    }

    public function test_update_changes_basic_information_fields(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $type = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'client']);
        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);
        $linkedUser = User::where('agency_id', $agency->id)->where('email', 'jamie@example.com')->firstOrFail();

        $this->actingAsAgency($admin)->patchJson("/api/agency/clients/{$client->id}/profile", [
            'first_name' => 'Kristin',
            'last_name' => 'Ben',
            'type_id' => [$type->id],
            'location_id' => [$location->id],
        ])->assertOk();

        $client->refresh();
        $this->assertSame('Kristin', $client->first_name);
        $this->assertSame('Ben', $client->last_name);
        $this->assertSame([$type->id], $client->type_id);
        $this->assertSame([$location->id], $client->location_id);
        $this->assertSame('Kristin', $linkedUser->fresh()->first_name);
    }

    public function test_update_profile_picture_can_be_changed(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        // Real clients must POST (not PATCH) when uploading a file: PHP only
        // parses multipart/form-data into $_FILES for POST requests, so a
        // genuine PATCH silently drops the file even though this test's HTTP
        // client wouldn't catch that (it injects files without going through
        // PHP's request parsing).
        $this->actingAsAgency($admin)
            ->post("/api/agency/clients/{$client->id}/profile", ['image' => $file], ['Content-Type' => 'multipart/form-data'])
            ->assertOk();

        $path = $client->fresh()->image;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_update_can_upload_a_dynamic_document_field(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity Documents',
                        'fields' => [
                            ['type' => 'file_upload', 'label' => 'ID Proof', 'name' => 'id_proof'],
                        ],
                    ]],
                ]],
            ],
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['id_proof' => 'form-submissions/client/old-id-proof.pdf'],
        ]);
        Storage::disk('public')->put('form-submissions/client/old-id-proof.pdf', 'old contents');

        $file = UploadedFile::fake()->create('id-proof.pdf', 128, 'application/pdf');

        // Same PATCH-drops-files caveat as the profile-picture test above.
        $this->actingAsAgency($admin)
            ->post("/api/agency/clients/{$client->id}/profile", ['id_proof' => $file], ['Content-Type' => 'multipart/form-data'])
            ->assertOk();

        $submission->refresh();
        $this->assertNotNull($submission->data['id_proof']);
        $this->assertNotSame('form-submissions/client/old-id-proof.pdf', $submission->data['id_proof']);
        Storage::disk('public')->assertExists($submission->data['id_proof']);
        Storage::disk('public')->assertMissing('form-submissions/client/old-id-proof.pdf');
    }

    public function test_admin_can_delete_a_clients_uploaded_document(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity Documents',
                        'fields' => [
                            ['type' => 'file_upload', 'label' => 'ID Proof', 'name' => 'id_proof'],
                        ],
                    ]],
                ]],
            ],
        ]);

        Storage::disk('public')->put('form-submissions/client/id-proof.png', 'contents');

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['id_proof' => 'form-submissions/client/id-proof.png'],
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/clients/{$client->id}/profile/documents/id_proof")
            ->assertOk();

        $submission->refresh();
        $this->assertNull($submission->data['id_proof']);
        Storage::disk('public')->assertMissing('form-submissions/client/id-proof.png');
    }

    public function test_deleting_a_document_returns_404_for_a_field_that_is_not_a_document(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Household',
                    'sections' => [[
                        'name' => 'Household Details',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Care Type Needed', 'name' => 'care_type'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['care_type' => 'Full-Time'],
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/clients/{$client->id}/profile/documents/care_type")
            ->assertNotFound();
    }

    public function test_deleting_a_document_removes_only_the_requested_file_from_a_list_files_field(): void
    {
        Storage::fake('public');

        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Documents',
                    'sections' => [[
                        'name' => 'Identity Documents',
                        'fields' => [
                            ['type' => 'list_files', 'label' => 'Certificates', 'name' => 'certificates'],
                        ],
                    ]],
                ]],
            ],
        ]);

        Storage::disk('public')->put('form-submissions/client/cert1.pdf', 'contents');
        Storage::disk('public')->put('form-submissions/client/cert2.pdf', 'contents');

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['certificates' => ['form-submissions/client/cert1.pdf', 'form-submissions/client/cert2.pdf']],
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/clients/{$client->id}/profile/documents/certificates", [
                'path' => 'form-submissions/client/cert1.pdf',
            ])
            ->assertOk();

        $submission->refresh();
        $this->assertSame(['form-submissions/client/cert2.pdf'], $submission->data['certificates']);
        Storage::disk('public')->assertMissing('form-submissions/client/cert1.pdf');
        Storage::disk('public')->assertExists('form-submissions/client/cert2.pdf');
    }

    public function test_update_writes_dynamic_answers_into_the_registration_submission(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Household',
                    'sections' => [[
                        'name' => 'Household Details',
                        'fields' => [
                            // Reuses a real `clients` column name, so it should also land on the model.
                            ['type' => 'text_box', 'label' => 'Mobile', 'name' => 'mobile'],
                            // A purely agency-defined field with no matching column.
                            ['type' => 'text_box', 'label' => 'Care Type Needed', 'name' => 'care_type', 'is_required' => true],
                        ],
                    ]],
                ]],
            ],
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['mobile' => '01710000000', 'care_type' => 'Full-Time'],
        ]);

        $this->actingAsAgency($admin)->patchJson("/api/agency/clients/{$client->id}/profile", [
            'mobile' => '01720000000',
            'care_type' => 'Part-Time',
        ])->assertOk();

        $submission->refresh();
        $this->assertSame('01720000000', $submission->data['mobile']);
        $this->assertSame('Part-Time', $submission->data['care_type']);
        $this->assertSame('01720000000', $client->fresh()->mobile);
    }

    public function test_update_rejects_blanking_a_required_dynamic_field(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Household',
                    'sections' => [[
                        'name' => 'Household Details',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Care Type Needed', 'name' => 'care_type', 'is_required' => true],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['care_type' => 'Full-Time'],
        ]);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/clients/{$client->id}/profile", ['care_type' => null])
            ->assertStatus(422);
    }

    public function test_update_rejects_duplicate_email(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Other', 'email' => 'other@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/clients/{$client->id}/profile", ['email' => 'other@example.com'])
            ->assertStatus(422);
    }

    public function test_update_accepts_same_email_for_same_client(): void
    {
        [$agency, $admin] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/clients/{$client->id}/profile", ['email' => 'jamie@example.com'])
            ->assertOk();
    }

    public function test_profile_is_scoped_to_the_authenticated_agency(): void
    {
        [, $admin] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other.test', 'subdomain_prefix' => 'other', 'email' => fake()->unique()->safeEmail()]);
        $foreignClient = Client::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/clients/{$foreignClient->id}/profile")
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
