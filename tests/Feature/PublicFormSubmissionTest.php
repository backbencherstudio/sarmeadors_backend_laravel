<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Location;
use App\Models\Status;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicFormSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_visitor_can_view_an_enabled_forms_schema_without_authentication(): void
    {
        [, $form] = $this->createAgencyAndForm();

        $this->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/forms/{$form->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $form->slug)
            ->assertJsonPath('data.schema.blocks.0.sections.0.fields.0.name', 'dob')
            ->assertJsonPath('data.base_fields.0.name', 'first_name')
            ->assertJsonPath('data.base_fields.2.name', 'email')
            ->assertJsonPath('data.base_fields.3.name', 'password')
            ->assertJsonPath('data.base_fields.4.name', 'image')
            ->assertJsonPath('data.base_fields.5.name', 'type_id')
            ->assertJsonPath('data.base_fields.6.name', 'location_id');
    }

    public function test_visitor_cannot_view_a_disabled_forms_schema(): void
    {
        [, $form] = $this->createAgencyAndForm(status: false);

        $this->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/forms/{$form->slug}")
            ->assertNotFound();
    }

    public function test_visitor_can_self_register_as_a_client_without_authentication(): void
    {
        [$agency, $form] = $this->createAgencyAndForm();

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                ],
            ]);

        $response->assertCreated()->assertJsonPath('data.entity', 'client');

        $this->assertSame(1, $agency->fresh()->total_clients);
    }

    public function test_public_submission_is_blocked_once_agency_reaches_max_clients(): void
    {
        [$agency, $form] = $this->createAgencyAndForm(maxClients: 1, totalClients: 1);

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                ],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Client limit exceeded for this agency.');

        $this->assertSame(0, Client::where('agency_id', $agency->id)->count());
    }

    public function test_visitor_can_set_their_own_password_type_and_location_on_self_registration(): void
    {
        [$agency, $form] = $this->createAgencyAndForm();

        $type = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'client']);
        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => 'jane.password@example.com',
                    'dob' => '1996-10-10',
                    'password' => 'Sup3rSecret',
                    'type_id' => [$type->id],
                    'location_id' => [$location->id],
                ],
            ]);

        $response->assertCreated();

        $client = Client::where('email', 'jane.password@example.com')->firstOrFail();
        $this->assertSame([$type->id], $client->type_id);
        $this->assertSame([$location->id], $client->location_id);
        $this->assertTrue(Hash::check('Sup3rSecret', $client->user->password));

        $submission = FormSubmission::where('entity_id', $client->id)->where('entity_type', 'client')->firstOrFail();
        $this->assertSame(['dob' => '1996-10-10'], $submission->data);
    }

    public function test_public_submission_rejects_type_id_and_location_id_from_another_agency(): void
    {
        [, $form] = $this->createAgencyAndForm();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignType = Type::create(['agency_id' => $otherAgency->id, 'name' => 'Nanny', 'type' => 'client']);

        $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                    'type_id' => [$foreignType->id],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['answers.type_id.0']]);
    }

    public function test_public_submission_requires_the_schemas_required_fields(): void
    {
        [, $form] = $this->createAgencyAndForm();

        $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => ['first_name' => 'Jane', 'email' => 'jane@example.com'],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['answers.dob']]);
    }

    public function test_public_submission_cannot_hijack_an_existing_users_account_via_user_id(): void
    {
        [$agency, $form] = $this->createAgencyAndForm();

        $victim = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Eve',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                    'user_id' => $victim->id,
                ],
            ]);

        $response->assertCreated();

        $client = Client::where('first_name', 'Eve')->firstOrFail();
        $this->assertNotSame($victim->id, $client->user_id);
    }

    public function test_public_submission_cannot_set_protected_columns(): void
    {
        [$agency, $form] = $this->createAgencyAndForm();

        $status = Status::create(['agency_id' => $agency->id, 'name' => 'Approved', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Eve',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                    'status_id' => [$status->id],
                    'tag_id' => [1],
                    'checklist_id' => [1],
                    'payment_status' => 'paid',
                    'stripe_customer_id' => 'cus_fake',
                    'agency_id' => $agency->id + 999,
                ],
            ]);

        $response->assertCreated();

        $client = Client::where('first_name', 'Eve')->firstOrFail();
        $this->assertNull($client->status_id);
        $this->assertNull($client->tag_id);
        $this->assertNull($client->checklist_id);
        $this->assertSame('pending', $client->payment_status);
        $this->assertNull($client->stripe_customer_id);
        $this->assertSame($agency->id, $client->agency_id);
    }

    public function test_visitor_can_view_an_enabled_candidate_forms_schema_without_authentication(): void
    {
        [, $form] = $this->createCandidateAgencyAndForm();

        $this->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/forms/{$form->slug}")
            ->assertOk()
            ->assertJsonPath('data.base_fields.0.name', 'first_name')
            ->assertJsonPath('data.base_fields.2.name', 'email')
            ->assertJsonPath('data.base_fields.3.name', 'password')
            ->assertJsonPath('data.base_fields.4.name', 'image')
            ->assertJsonPath('data.base_fields.5.name', 'type_id')
            ->assertJsonPath('data.base_fields.6.name', 'location_id');
    }

    public function test_visitor_can_self_register_as_a_candidate_without_authentication(): void
    {
        [$agency, $form] = $this->createCandidateAgencyAndForm();

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                ],
            ]);

        $response->assertCreated()->assertJsonPath('data.entity', 'candidate');

        $this->assertSame(1, $agency->fresh()->total_candidates);
    }

    public function test_public_submission_is_blocked_once_agency_reaches_max_candidates(): void
    {
        [$agency, $form] = $this->createCandidateAgencyAndForm(maxCandidates: 1, totalCandidates: 1);

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                ],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Candidate limit exceeded for this agency.');

        $this->assertSame(0, Candidate::where('agency_id', $agency->id)->count());
    }

    public function test_candidate_can_set_their_own_password_type_and_location_on_self_registration(): void
    {
        [$agency, $form] = $this->createCandidateAgencyAndForm();

        $type = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'candidate']);
        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);

        $response = $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => 'jane.candidate@example.com',
                    'dob' => '1996-10-10',
                    'password' => 'Sup3rSecret',
                    'type_id' => [$type->id],
                    'location_id' => [$location->id],
                ],
            ]);

        $response->assertCreated();

        $candidate = Candidate::where('email', 'jane.candidate@example.com')->firstOrFail();
        $this->assertSame([$type->id], $candidate->type_id);
        $this->assertSame([$location->id], $candidate->location_id);
        $this->assertTrue(Hash::check('Sup3rSecret', $candidate->user->password));
    }

    public function test_candidate_public_submission_rejects_type_id_from_another_agency(): void
    {
        [, $form] = $this->createCandidateAgencyAndForm();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other-candidate.test',
            'subdomain_prefix' => 'other-candidate',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignType = Type::create(['agency_id' => $otherAgency->id, 'name' => 'Nanny', 'type' => 'candidate']);

        $this->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/forms/{$form->slug}/submit", [
                'answers' => [
                    'first_name' => 'Jane',
                    'email' => fake()->unique()->safeEmail(),
                    'dob' => '1996-10-10',
                    'type_id' => [$foreignType->id],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['answers.type_id.0']]);
    }

    private function createCandidateAgencyAndForm(bool $status = true, int $maxCandidates = 10, int $totalCandidates = 0): array
    {
        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
            'max_candidates' => $maxCandidates,
            'total_candidates' => $totalCandidates,
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Candidate Registration Form',
            'slug' => 'candidate-registration-form',
            'entity' => 'candidate',
            'application_type' => 'registration',
            'user_type' => 'candidate',
            'status' => $status,
            'schema' => [
                'blocks' => [[
                    'name' => 'Personal Information',
                    'sections' => [[
                        'name' => 'Contact and Personal Info',
                        'fields' => [
                            ['type' => 'date_picker', 'label' => 'Date of Birth', 'name' => 'dob', 'is_required' => true],
                            ['type' => 'text_box', 'label' => 'NID', 'name' => 'nid', 'is_required' => false],
                        ],
                    ]],
                ]],
            ],
        ]);

        return [$agency, $form];
    }

    private function createAgencyAndForm(bool $status = true, int $maxClients = 10, int $totalClients = 0): array
    {
        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
            'max_clients' => $maxClients,
            'total_clients' => $totalClients,
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration Form',
            'slug' => 'client-registration-form',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'status' => $status,
            'schema' => [
                'blocks' => [[
                    'name' => 'Personal Information',
                    'sections' => [[
                        'name' => 'Contact and Personal Info',
                        'fields' => [
                            ['type' => 'date_picker', 'label' => 'Date of Birth', 'name' => 'dob', 'is_required' => true],
                            ['type' => 'text_box', 'label' => 'NID', 'name' => 'nid', 'is_required' => false],
                        ],
                    ]],
                ]],
            ],
        ]);

        return [$agency, $form];
    }
}
