<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyFormBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_agency_can_create_client_registration_form_with_nested_schema(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $response = $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Client Registration',
                'application_type' => 'registration',
                'user_type' => 'client',
                'schema' => $this->registrationSchema(),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entity', 'client')
            ->assertJsonPath('data.application_type', 'registration')
            ->assertJsonPath('data.schema.blocks.0.name', 'Contact & Address')
            ->assertJsonPath('data.schema.blocks.0.sections.0.fields.0.name', 'first_name');

        // serials & keys get backfilled during normalization
        $this->assertNotEmpty($response->json('data.schema.blocks.0.key'));
        $this->assertSame(1, $response->json('data.schema.blocks.0.serial'));

        $this->assertDatabaseHas('forms', [
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'entity' => 'client',
            'user_type' => 'client',
        ]);
    }

    public function test_rejects_unsupported_short_term_job_posting(): void
    {
        [, $user] = $this->createAgencyScenario();

        $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Shift Job',
                'application_type' => 'job_posting',
                'job_type' => 'short_term',
            ])
            ->assertStatus(422);
    }

    public function test_agency_can_list_and_load_forms(): void
    {
        [, $user] = $this->createAgencyScenario();

        $slug = $this->createForm($user)->json('data.slug');

        $this->actingAsAgency($user)
            ->getJson('/api/agency/forms')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAsAgency($user)
            ->getJson("/api/agency/forms/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.schema.blocks.0.sections.0.fields.1.name', 'email');
    }

    public function test_client_registration_submission_creates_client(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $slug = $this->createForm($user)->json('data.slug');

        $response = $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$slug}/submit", [
                'answers' => [
                    'first_name' => 'John',
                    'email' => 'john@example.com',
                    'favorite_color' => 'Blue',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entity', 'client')
            ->assertJsonPath('data.record.email', 'john@example.com');

        $this->assertDatabaseHas('clients', [
            'agency_id' => $agency->id,
            'email' => 'john@example.com',
            'first_name' => 'John',
        ]);

        $client = Client::where('email', 'john@example.com')->first();

        $this->assertDatabaseHas('form_submissions', [
            'entity_id' => $client->id,
            'entity_type' => 'client',
        ]);

        // the full answer payload (including non-column fields) is preserved
        $submission = $client->submissions()->first();
        $this->assertSame('Blue', $submission->data['favorite_color']);
    }

    public function test_candidate_registration_submission_creates_candidate(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $slug = $this->createForm($user, 'candidate')->json('data.slug');

        $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => 'jane@example.com',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.entity', 'candidate');

        $this->assertDatabaseHas('candidates', [
            'agency_id' => $agency->id,
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
        ]);
    }

    public function test_submission_fails_when_required_dynamic_field_missing(): void
    {
        [, $user] = $this->createAgencyScenario();

        $slug = $this->createForm($user)->json('data.slug');

        $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$slug}/submit", [
                'answers' => [
                    'email' => 'john@example.com',
                    'favorite_color' => 'Blue',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['answers.first_name']]);
    }

    public function test_long_term_job_posting_submission_creates_job(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Parent',
            'email' => 'parent@example.com',
        ]);

        $slug = $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Post Long-term Job',
                'application_type' => 'job_posting',
                'job_type' => 'long_term',
                'schema' => [
                    'blocks' => [[
                        'name' => 'Job Details',
                        'sections' => [[
                            'name' => 'Untitled Section',
                            'fields' => [
                                ['type' => 'text_box', 'label' => 'Title', 'name' => 'title', 'is_required' => true],
                                ['type' => 'text_area', 'label' => 'Description', 'name' => 'description'],
                                ['type' => 'text_box', 'label' => 'Job Address', 'name' => 'job_address'],
                            ],
                        ]],
                    ]],
                ],
            ])
            ->json('data.slug');

        $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$slug}/submit", [
                'client_id' => $client->id,
                'answers' => [
                    'title' => 'Live-in Nanny',
                    'description' => 'Full time nanny needed',
                    'job_address' => '123 Main St',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.entity', 'long_term_job');

        $this->assertDatabaseHas('long_term_jobs', [
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Live-in Nanny',
        ]);
    }

    public function test_long_term_job_submission_requires_client_id(): void
    {
        [, $user] = $this->createAgencyScenario();

        $slug = $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Post Long-term Job',
                'application_type' => 'job_posting',
                'job_type' => 'long_term',
                'schema' => [
                    'blocks' => [[
                        'name' => 'Job Details',
                        'sections' => [[
                            'name' => 'Untitled Section',
                            'fields' => [
                                ['type' => 'text_box', 'label' => 'Title', 'name' => 'title', 'is_required' => true],
                            ],
                        ]],
                    ]],
                ],
            ])
            ->json('data.slug');

        $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$slug}/submit", [
                'answers' => ['title' => 'Live-in Nanny'],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['client_id']]);
    }

    public function test_agency_cannot_access_or_submit_another_agencys_form(): void
    {
        [, $user] = $this->createAgencyScenario();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignForm = Form::create([
            'agency_id' => $otherAgency->id,
            'name' => 'Foreign Form',
            'slug' => 'foreign-form',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'schema' => $this->registrationSchema(),
        ]);

        $this->actingAsAgency($user)
            ->getJson("/api/agency/forms/{$foreignForm->slug}")
            ->assertNotFound();

        $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$foreignForm->slug}/submit", [
                'answers' => ['first_name' => 'X', 'email' => 'x@example.com'],
            ])
            ->assertNotFound();
    }

    public function test_form_creation_rejects_unsupported_field_type(): void
    {
        [, $user] = $this->createAgencyScenario();

        $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Client Registration',
                'application_type' => 'registration',
                'user_type' => 'client',
                'schema' => [
                    'blocks' => [[
                        'name' => 'Contact & Address',
                        'sections' => [[
                            'name' => 'Untitled Section',
                            'fields' => [
                                ['type' => 'made_up_widget', 'label' => 'Mystery'],
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['schema.blocks.0.sections.0.fields.0.type']]);
    }

    public function test_form_creation_rejects_block_without_name(): void
    {
        [, $user] = $this->createAgencyScenario();

        $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Client Registration',
                'application_type' => 'registration',
                'user_type' => 'client',
                'schema' => [
                    'blocks' => [[
                        'sections' => [[
                            'name' => 'Untitled Section',
                            'fields' => [
                                ['type' => 'text_box', 'label' => 'First Name'],
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['schema.blocks.0.name']]);
    }

    public function test_long_term_job_form_creation_rejects_field_without_label(): void
    {
        [, $user] = $this->createAgencyScenario();

        $this->actingAsAgency($user)
            ->postJson('/api/agency/forms', [
                'name' => 'Post Long-term Job',
                'application_type' => 'job_posting',
                'job_type' => 'long_term',
                'schema' => [
                    'blocks' => [[
                        'name' => 'Job Details',
                        'sections' => [[
                            'name' => 'Untitled Section',
                            'fields' => [
                                ['type' => 'text_box'],
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['schema.blocks.0.sections.0.fields.0.label']]);
    }

    public function test_slug_is_unique_per_agency_not_globally(): void
    {
        [, $user] = $this->createAgencyScenario();

        $slug = $this->createForm($user)->json('data.slug');

        $this->assertSame('client-registration', $slug);

        // A different agency can hold the same slug — no global collision.
        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $otherForm = Form::create([
            'agency_id' => $otherAgency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'schema' => $this->registrationSchema(),
        ]);

        $this->assertSame('client-registration', $otherForm->slug);
        $this->assertSame(2, Form::where('slug', 'client-registration')->count());
    }

    public function test_duplicate_form_name_within_agency_is_suffixed(): void
    {
        [, $user] = $this->createAgencyScenario();

        $first = $this->createForm($user)->json('data.slug');
        $second = $this->createForm($user)->json('data.slug');

        $this->assertSame('client-registration', $first);
        $this->assertSame('client-registration-2', $second);
    }

    public function test_agency_can_disable_and_enable_a_form(): void
    {
        [, $user] = $this->createAgencyScenario();

        $form = Form::where('slug', $this->createForm($user)->json('data.slug'))->first();

        // Toggle off (no body -> flips from enabled to disabled).
        $this->actingAsAgency($user)
            ->patchJson("/api/agency/forms/{$form->id}/status")
            ->assertOk()
            ->assertJsonPath('message', 'Form disabled')
            ->assertJsonPath('data.status', false);

        $this->assertDatabaseHas('forms', ['id' => $form->id, 'status' => false]);

        // Explicitly enable.
        $this->actingAsAgency($user)
            ->patchJson("/api/agency/forms/{$form->id}/status", ['status' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Form enabled')
            ->assertJsonPath('data.status', true);
    }

    public function test_disabled_form_rejects_submission(): void
    {
        [, $user] = $this->createAgencyScenario();

        $slug = $this->createForm($user)->json('data.slug');
        $form = Form::where('slug', $slug)->first();

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/forms/{$form->id}/status", ['status' => false])
            ->assertOk();

        $this->actingAsAgency($user)
            ->postJson("/api/agency/forms/{$slug}/submit", [
                'answers' => ['first_name' => 'John', 'email' => 'john@example.com'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This form is currently disabled');

        $this->assertDatabaseMissing('clients', ['email' => 'john@example.com']);
    }

    public function test_status_filter_lists_only_matching_forms(): void
    {
        [, $user] = $this->createAgencyScenario();

        $enabledSlug = $this->createForm($user)->json('data.slug');
        $disabled = Form::where('slug', $this->createForm($user, 'candidate')->json('data.slug'))->first();

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/forms/{$disabled->id}/status", ['status' => false])
            ->assertOk();

        $this->actingAsAgency($user)
            ->getJson('/api/agency/forms?status=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $enabledSlug);
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }

    private function createForm(User $user, string $userType = 'client')
    {
        return $this->actingAsAgency($user)->postJson('/api/agency/forms', [
            'name' => ucfirst($userType).' Registration',
            'application_type' => 'registration',
            'user_type' => $userType,
            'schema' => $this->registrationSchema(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationSchema(): array
    {
        return [
            'blocks' => [[
                'name' => 'Contact & Address',
                'description' => 'Add contact and address information of parents.',
                'sections' => [[
                    'name' => 'Untitled Section',
                    'fields' => [
                        ['type' => 'text_box', 'label' => 'First Name', 'name' => 'first_name', 'is_required' => true],
                        ['type' => 'email', 'label' => 'Email', 'name' => 'email', 'is_required' => true],
                        ['type' => 'text_box', 'label' => 'Favorite Color', 'name' => 'favorite_color'],
                    ],
                ]],
            ]],
        ];
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

        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        Role::create([
            'name' => 'agency_admin',
            'guard_name' => 'web',
        ]);

        $user->assignRole('agency_admin');

        return [$agency, $user];
    }
}
