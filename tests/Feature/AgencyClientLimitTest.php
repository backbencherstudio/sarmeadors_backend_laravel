<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CheckList;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Location;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyClientLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_creation_is_blocked_once_agency_reaches_max_clients(): void
    {
        [$agency, $user, $form] = $this->createAgencyScenario(maxClients: 1, totalClients: 1);

        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'form_id' => $form->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Client limit exceeded for this agency.');

        $this->assertSame(0, Client::where('agency_id', $agency->id)->count());
        $this->assertSame(1, $agency->fresh()->total_clients);
    }

    public function test_client_creation_without_form_id_succeeds_for_the_quick_add_modal(): void
    {
        [$agency, $user] = $this->createAgencyScenario(maxClients: 10, totalClients: 0);

        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '01700000000',
            'hear_about_us' => 'Referral',
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $client = Client::where('agency_id', $agency->id)->first();
        $this->assertNotNull($client);
        $this->assertSame('Referral', $client->hear_about_us);
        $this->assertSame(1, $agency->fresh()->total_clients);
    }

    public function test_client_creation_rejects_form_id_from_another_agency(): void
    {
        [, $user] = $this->createAgencyScenario(maxClients: 10, totalClients: 0);

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $otherForm = Form::create([
            'agency_id' => $otherAgency->id,
            'name' => 'Client Registration',
            'slug' => 'other-client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'schema' => [],
        ]);

        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'form_id' => $otherForm->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.form_id.0', 'The selected form id is invalid.');

        $this->assertSame(0, Client::count());
    }

    public function test_client_creation_succeeds_and_increments_total_clients_when_under_limit(): void
    {
        [$agency, $user, $form] = $this->createAgencyScenario(maxClients: 2, totalClients: 0);

        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'form_id' => $form->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $this->assertSame(1, $agency->fresh()->total_clients);
    }

    public function test_client_creation_rejects_lookup_ids_belonging_to_another_agency(): void
    {
        [$agency, $user] = $this->createAgencyScenario(maxClients: 10, totalClients: 0);

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other-lookup.test',
            'subdomain_prefix' => 'other-lookup',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignType = Type::create(['agency_id' => $otherAgency->id, 'name' => 'Nanny', 'type' => 'client']);

        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
            'type_id' => [$foreignType->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Client::count());
    }

    public function test_client_creation_accepts_valid_lookup_ids_scoped_to_the_agency(): void
    {
        [$agency, $user] = $this->createAgencyScenario(maxClients: 10, totalClients: 0);

        $nanny = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'client']);
        $miami = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);
        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);
        $background = CheckList::create(['agency_id' => $agency->id, 'name' => 'Background Check', 'type' => 'client', 'status' => 1]);
        $vip = Tag::create(['agency_id' => $agency->id, 'name' => 'VIP', 'type' => 'client', 'status' => 1]);

        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
            'type_id' => [$nanny->id],
            'location_id' => [$miami->id],
            'checklist_id' => [$background->id],
            'tag_id' => [$vip->id],
            'status_id' => [$active->id],
        ]);

        $response->assertOk();

        $client = Client::firstOrFail();
        $this->assertSame([$nanny->id], $client->type_id);
        $this->assertSame([$miami->id], $client->location_id);
        $this->assertSame([$background->id], $client->checklist_id);
        $this->assertSame([$vip->id], $client->tag_id);
        $this->assertSame([$active->id], $client->status_id);
        $this->assertNotNull($client->status_changed_at);
    }

    public function test_client_creation_form_field_enforces_both_required_and_custom_validation_rules(): void
    {
        [$agency, $user, $form] = $this->createAgencyScenario(maxClients: 10, totalClients: 0);

        $field = FormField::create([
            'form_id' => $form->id,
            'label' => 'Emergency Email',
            'name' => 'emergency_email',
            'type' => 'text',
            'is_required' => true,
            'validation_rules' => 'email',
            'status' => true,
        ]);

        // Present but invalid per the field's own "email" rule: must fail even
        // though the field is also required (required must not swallow it).
        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'form_id' => $form->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
            'fields' => [
                $field->id => 'not-an-email',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Client::count());

        // Missing entirely: must fail on "required".
        $response = $this->actingAsAgency($user)->postJson('/api/agency/clients', [
            'form_id' => $form->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Client::count());
    }

    private function createAgencyScenario(int $maxClients, int $totalClients): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
            'max_clients' => $maxClients,
            'total_clients' => $totalClients,
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

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Client Registration',
            'slug' => 'client-registration',
            'entity' => 'client',
            'application_type' => 'registration',
            'user_type' => 'client',
            'schema' => [],
        ]);

        return [$agency, $user, $form];
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
