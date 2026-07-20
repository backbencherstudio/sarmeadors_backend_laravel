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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_returns_basic_information_block_from_client_columns(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $response = $this->actingAsClient($user)->getJson('/api/client/profile');

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.form_id', null)
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.key', 'first_name')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Jenny')
            ->assertJsonCount(1, 'data.blocks');

        $fields = collect($response->json('data.blocks.0.sections.0.fields'))->keyBy('key');
        $this->assertNotContains('password', $fields->keys());
    }

    public function test_show_returns_section_wise_registration_form_answers(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

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
                    'name' => 'Care Preferences',
                    'sections' => [[
                        'name' => 'Care Preferences',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Care Type', 'name' => 'care_type', 'is_required' => true],
                            ['type' => 'text_box', 'label' => 'Notes', 'name' => 'notes'],
                        ],
                    ]],
                ]],
            ],
        ]);

        FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['care_type' => 'Full-Time', 'notes' => 'Two toddlers'],
        ]);

        $response = $this->actingAsClient($user)->getJson('/api/client/profile');

        $response->assertOk()
            ->assertJsonPath('data.form_id', $form->id)
            ->assertJsonPath('data.form_name', 'Client Registration')
            ->assertJsonPath('data.blocks.0.name', 'Basic Information')
            ->assertJsonPath('data.blocks.1.name', 'Care Preferences')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.key', 'care_type')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.value', 'Full-Time')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.key', 'notes')
            ->assertJsonPath('data.blocks.1.sections.0.fields.1.value', 'Two toddlers');
    }

    public function test_show_filters_to_a_single_block_when_a_slug_is_given(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

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
                    'name' => 'Care Preferences',
                    'sections' => [[
                        'name' => 'Care Preferences',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Care Type', 'name' => 'care_type', 'is_required' => true],
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

        $response = $this->actingAsClient($user)->getJson('/api/client/profile?slug=care-preferences');

        $response->assertOk()
            ->assertJsonCount(1, 'data.blocks')
            ->assertJsonPath('data.blocks.0.name', 'Care Preferences')
            ->assertJsonPath('data.blocks.0.slug', 'care-preferences')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Full-Time');
    }

    public function test_show_filters_to_the_basic_information_block_by_slug(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $response = $this->actingAsClient($user)->getJson('/api/client/profile?slug=basic-information');

        $response->assertOk()
            ->assertJsonCount(1, 'data.blocks')
            ->assertJsonPath('data.blocks.0.slug', 'basic-information')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Jenny');
    }

    public function test_show_returns_an_empty_blocks_list_for_an_unknown_slug(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->actingAsClient($user)
            ->getJson('/api/client/profile?slug=does-not-exist')
            ->assertOk()
            ->assertJsonCount(0, 'data.blocks');
    }

    public function test_client_can_update_profile_with_image_and_locations(): void
    {
        Storage::fake('public');

        [$agency, $user, $client] = $this->createClientScenario();
        $location = Location::create(['agency_id' => $agency->id, 'location' => 'Manhattan']);

        $response = $this->actingAsClient($user)
            ->post('/api/client/profile', [
                'first_name' => 'Jennifer',
                'last_name' => 'Wilson',
                'mobile' => '+15550001111',
                'location_id' => [$location->id],
                'image' => UploadedFile::fake()->image('avatar.png'),
            ], ['Content-Type' => 'multipart/form-data']);

        $response->assertOk()
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'Jennifer');

        $fields = collect($response->json('data.blocks.0.sections.0.fields'))->keyBy('key');
        $this->assertNotNull($fields['image']['value']);
        $this->assertSame([$location->id], $fields['location_id']['value']);

        $this->assertSame('Jennifer', $user->fresh()->first_name);
        $this->assertSame('Jennifer', $client->fresh()->first_name);
        Storage::disk('public')->assertExists($client->fresh()->image);
    }

    public function test_client_can_update_profile_with_address(): void
    {
        [, $user, $client] = $this->createClientScenario();

        $response = $this->actingAsClient($user)->postJson('/api/client/profile', [
            'nationality' => 'Americans',
            'street_address' => '26 Berkshire Ave.',
            'city' => 'Atlantic City',
            'province' => 'NJ',
            'postal_code' => '08401',
            'country' => 'USA',
        ]);

        $response->assertOk();

        $client->refresh();
        $this->assertSame('Americans', $client->nationality);
        $this->assertSame('26 Berkshire Ave.', $client->street_address);
        $this->assertSame('Atlantic City', $client->city);
        $this->assertSame('NJ', $client->province);
        $this->assertSame('08401', $client->postal_code);
        $this->assertSame('USA', $client->country);
    }

    public function test_update_supports_the_type_id_base_field(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();
        $type = Type::create(['agency_id' => $agency->id, 'name' => 'Full-Time', 'type' => 'client']);

        $this->actingAsClient($user)->postJson('/api/client/profile', [
            'type_id' => [$type->id],
        ])->assertOk();

        $this->assertSame([$type->id], $client->fresh()->type_id);
    }

    public function test_update_does_not_change_the_login_email(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->actingAsClient($user)->postJson('/api/client/profile', [
            'first_name' => 'Jennifer',
            'email' => 'someone-else@example.com',
        ])->assertOk();

        $this->assertSame($user->email, $client->fresh()->email);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_update_rejects_locations_from_another_agency(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignLocation = Location::create(['agency_id' => $otherAgency->id, 'location' => 'Foreign']);

        $this->actingAsClient($user)
            ->postJson('/api/client/profile', ['location_id' => [$foreignLocation->id]])
            ->assertStatus(422);
    }

    public function test_update_writes_dynamic_answers_into_the_registration_submission(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

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
                    'name' => 'Care Preferences',
                    'sections' => [[
                        'name' => 'Care Preferences',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Care Type', 'name' => 'care_type', 'is_required' => true],
                        ],
                    ]],
                ]],
            ],
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'entity_id' => $client->id,
            'entity_type' => 'client',
            'data' => ['care_type' => 'Part-Time'],
        ]);

        $response = $this->actingAsClient($user)->postJson('/api/client/profile', [
            'care_type' => 'Full-Time',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.value', 'Full-Time');

        $submission->refresh();
        $this->assertSame('Full-Time', $submission->data['care_type']);
    }

    public function test_update_rejects_blanking_a_required_dynamic_field(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

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
                    'name' => 'Care Preferences',
                    'sections' => [[
                        'name' => 'Care Preferences',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Care Type', 'name' => 'care_type', 'is_required' => true],
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

        $this->actingAsClient($user)
            ->postJson('/api/client/profile', ['care_type' => null])
            ->assertStatus(422);
    }

    public function test_client_can_update_password_with_valid_rules(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->actingAsClient($user)->putJson('/api/client/profile/password', [
            'current_password' => 'not-the-password',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertStatus(422)->assertJsonPath('message', 'Current password is incorrect.');

        $this->actingAsClient($user)->putJson('/api/client/profile/password', [
            'current_password' => 'password',
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertStatus(422);

        $this->actingAsClient($user)->putJson('/api/client/profile/password', [
            'current_password' => 'password',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_client_can_delete_account_with_password_confirmation(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $this->actingAsClient($user)->deleteJson('/api/client/profile', [
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonPath('message', 'Password is incorrect.');

        $this->actingAsClient($user)->deleteJson('/api/client/profile', [
            'password' => 'password',
        ])->assertOk()->assertJsonPath('message', 'Account deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    private function createClientScenario(): array
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
            'first_name' => 'Jenny',
            'last_name' => 'Wilson',
            'password' => Hash::make('password'),
        ]);

        Role::findOrCreate('client', 'api');
        $user->assignRole(Role::where('name', 'client')->where('guard_name', 'api')->first());

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Jenny',
            'last_name' => 'Wilson',
            'email' => $user->email,
            'mobile' => '+1433467689',
        ]);

        return [$agency, $user, $client];
    }

    private function actingAsClient(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
