<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyClientGlobalSetting;
use App\Models\Candidate;
use App\Models\CheckList;
use App\Models\Client;
use App\Models\Location;
use App\Models\Status;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyClientDashboardManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_index_filters_by_type_location_and_status(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $nanny = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'client']);
        $housekeeper = Type::create(['agency_id' => $agency->id, 'name' => 'Housekeeper', 'type' => 'client']);

        $miami = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);
        $newYork = Location::create(['agency_id' => $agency->id, 'location' => 'New York', 'status' => 1]);

        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);
        $inactive = Status::create(['agency_id' => $agency->id, 'name' => 'Inactive', 'color' => '#ff0000', 'serial' => 2, 'type' => 'client']);

        $match = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alice',
            'last_name' => 'Walker',
            'email' => 'alice@example.com',
            'type_id' => [$nanny->id],
            'location_id' => [$miami->id],
            'status_id' => [$active->id],
        ]);

        Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Bob',
            'last_name' => 'Stone',
            'email' => 'bob@example.com',
            'type_id' => [$housekeeper->id],
            'location_id' => [$newYork->id],
            'status_id' => [$inactive->id],
        ]);

        $response = $this->actingAsAgency($user)->getJson('/api/agency/clients?'.http_build_query([
            'type_ids' => $nanny->id,
            'location_ids' => $miami->id,
            'status_ids' => $active->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.type', ['Nanny'])
            ->assertJsonPath('data.0.location', ['Miami']);
    }

    public function test_client_index_quick_search_with_name_field_matches_first_last_and_full_name(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        AgencyClientGlobalSetting::create([
            'agency_id' => $agency->id,
            'settings' => ['dashboard' => ['quick_search_field' => 'name']],
        ]);

        $alice = Client::create(['agency_id' => $agency->id, 'first_name' => 'Alice', 'last_name' => 'Walker', 'email' => 'alice@example.com']);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Bob', 'last_name' => 'Stone', 'email' => 'bob@example.com']);

        // Matches on last_name alone.
        $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?quick_search=Walker')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $alice->id);

        // Matches on the full "first last" combination.
        $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?quick_search='.urlencode('Alice Walker'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $alice->id);
    }

    public function test_client_index_fixed_search_box_still_works_when_quick_search_field_is_narrowed(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        // Admin narrows the configurable quick-search box down to phone number only.
        AgencyClientGlobalSetting::create([
            'agency_id' => $agency->id,
            'settings' => ['dashboard' => ['quick_search_field' => 'phone_number']],
        ]);

        $stephanie = Client::create(['agency_id' => $agency->id, 'first_name' => 'Stephanie', 'last_name' => 'Wilson', 'email' => 'stephanie@example.com', 'mobile' => '5550001']);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Bob', 'last_name' => 'Stone', 'email' => 'bob@example.com', 'mobile' => '5550002']);

        // The fixed top "Search by Name, Email or Phone Number" box must still
        // match by full name even though the configurable field is now "phone_number".
        $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?search='.urlencode('Stephanie Wilson'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $stephanie->id);

        // Meanwhile the configurable box now only searches phone_number.
        $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?quick_search='.urlencode('Stephanie Wilson'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?quick_search=5550001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $stephanie->id);
    }

    public function test_client_index_default_search_matches_full_name_with_no_quick_search_field_set(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $stephanie = Client::create(['agency_id' => $agency->id, 'first_name' => 'Stephanie', 'last_name' => 'Wilson', 'email' => 'stephanie@example.com']);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Bob', 'last_name' => 'Stone', 'email' => 'bob@example.com']);

        $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?search='.urlencode('Stephanie Wilson'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $stephanie->id);
    }

    public function test_client_index_can_hide_statuses(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);
        $rejected = Status::create(['agency_id' => $agency->id, 'name' => 'Rejected', 'color' => '#ff0000', 'serial' => 2, 'type' => 'client']);

        Client::create([
            'agency_id' => $agency->id, 'first_name' => 'Alice', 'email' => 'alice@example.com',
            'status_id' => [$active->id],
        ]);
        Client::create([
            'agency_id' => $agency->id, 'first_name' => 'Bob', 'email' => 'bob@example.com',
            'status_id' => [$rejected->id],
        ]);

        $response = $this->actingAsAgency($user)
            ->getJson('/api/agency/clients?hide_status_ids='.$rejected->id);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Alice');
    }

    public function test_client_status_statistics_returns_counts_and_percentages(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);
        $inactive = Status::create(['agency_id' => $agency->id, 'name' => 'Inactive', 'color' => '#ff0000', 'serial' => 2, 'type' => 'client']);

        Client::create(['agency_id' => $agency->id, 'first_name' => 'Alice', 'email' => 'alice@example.com', 'status_id' => [$active->id]]);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Bob', 'email' => 'bob@example.com', 'status_id' => [$active->id]]);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Carl', 'email' => 'carl@example.com', 'status_id' => [$inactive->id]]);
        Client::create(['agency_id' => $agency->id, 'first_name' => 'Dana', 'email' => 'dana@example.com']);

        $response = $this->actingAsAgency($user)->getJson('/api/agency/clients/status-statistics');

        $response->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.name', 'Active')
            ->assertJsonPath('data.0.count', 2)
            ->assertJsonPath('data.0.percentage', 50)
            ->assertJsonPath('data.1.name', 'Inactive')
            ->assertJsonPath('data.1.count', 1)
            ->assertJsonPath('data.1.percentage', 25);
    }

    public function test_candidate_index_filters_by_type_location_and_status(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $nanny = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'candidate']);
        $miami = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);
        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'candidate']);

        $match = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Kelsey',
            'email' => 'kelsey@example.com',
            'type_id' => [$nanny->id],
            'location_id' => [$miami->id],
            'status_id' => [$active->id],
        ]);

        Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Maria',
            'email' => 'maria@example.com',
        ]);

        $response = $this->actingAsAgency($user)->getJson('/api/agency/candidates?'.http_build_query([
            'type_ids' => $nanny->id,
            'location_ids' => $miami->id,
            'status_ids' => $active->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.type', ['Nanny'])
            ->assertJsonPath('data.0.location', 'Miami');
    }

    public function test_status_reasons_are_synced_when_updated(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $rejected = Status::create(['agency_id' => $agency->id, 'name' => 'Rejected', 'color' => '#ff0000', 'serial' => 1, 'type' => 'client']);
        $inactive = Status::create([
            'agency_id' => $agency->id, 'name' => 'Inactive', 'color' => '#999999', 'serial' => 2, 'type' => 'client',
            'any_reason' => 1, 'reason' => 'Old reason',
        ]);
        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 3, 'type' => 'client']);

        // Select only "Rejected" as needing a reason; "Inactive" should be reset.
        $response = $this->actingAsAgency($user)->postJson('/api/agency/status-reasons', [
            'type' => 'client',
            'statuses' => [
                ['id' => $rejected->id, 'reason' => "Did not pass interview\nFailed background check"],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('statuses', [
            'id' => $rejected->id,
            'any_reason' => 1,
            'reason' => "Did not pass interview\nFailed background check",
        ]);
        $this->assertDatabaseHas('statuses', [
            'id' => $inactive->id,
            'any_reason' => 0,
            'reason' => null,
        ]);
        $this->assertDatabaseHas('statuses', [
            'id' => $active->id,
            'any_reason' => 0,
        ]);
    }

    public function test_client_show_returns_resolved_status_and_duration_label(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);
        $chicago = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Pristia',
            'last_name' => 'Candra',
            'email' => 'pristia@example.com',
            'mobile' => '5551234',
            'status_id' => [$active->id],
            'status_changed_at' => now()->subHours(2)->subMinutes(44),
            'location_id' => [$chicago->id],
        ]);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.first_name', 'Pristia')
            ->assertJsonPath('data.last_name', 'Candra')
            ->assertJsonPath('data.full_name', 'Pristia Candra')
            ->assertJsonPath('data.location_id', [$chicago->id])
            ->assertJsonPath('data.locations', ['Chicago'])
            ->assertJsonPath('data.status.name', 'Active')
            ->assertJsonPath('data.status.color', '#00ff00')
            ->assertJsonPath('data.status_duration_label', '2 hours, 44 minutes');
    }

    public function test_client_show_is_scoped_to_the_authenticated_agency(): void
    {
        [, $user] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other2.test', 'subdomain_prefix' => 'other2', 'email' => fake()->unique()->safeEmail()]);

        $foreignClient = Client::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($user)
            ->getJson("/api/agency/clients/{$foreignClient->id}")
            ->assertStatus(404);
    }

    public function test_client_update_status_records_status_changed_at(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $active = Status::create(['agency_id' => $agency->id, 'name' => 'Active', 'color' => '#00ff00', 'serial' => 1, 'type' => 'client']);
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/status", ['status_id' => $active->id])
            ->assertOk();

        $this->assertNotNull($client->fresh()->status_changed_at);
    }

    public function test_client_profile_can_be_updated(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $chicago = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'last_name' => 'Candra', 'email' => 'pristia@example.com', 'mobile' => '5550000']);

        $response = $this->actingAsAgency($user)->postJson("/api/agency/clients/{$client->id}", [
            'first_name' => 'Pristia',
            'last_name' => 'Updated',
            'email' => 'ckctm12@gmail.com',
            'mobile' => '+1433467689',
            'location_id' => [$chicago->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.full_name', 'Pristia Updated')
            ->assertJsonPath('data.email', 'ckctm12@gmail.com')
            ->assertJsonPath('data.mobile', '+1433467689')
            ->assertJsonPath('data.location_id', [$chicago->id]);

        $fresh = $client->fresh();
        $this->assertSame('Updated', $fresh->last_name);
        $this->assertSame('ckctm12@gmail.com', $fresh->email);
    }

    public function test_client_profile_update_accepts_comma_separated_location_id_from_multipart_forms(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $chicago = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);
        $miami = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        // Postman/multipart forms send this as a single "3,4" string, not a real array.
        $response = $this->actingAsAgency($user)->post("/api/agency/clients/{$client->id}", [
            'first_name' => 'Niaz Ahmed',
            'last_name' => 'Nayeem',
            'email' => 'niaz@gmail.com',
            'mobile' => '01886509310',
            'location_id' => "{$chicago->id},{$miami->id}",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.location_id', [$chicago->id, $miami->id]);

        $this->assertSame([$chicago->id, $miami->id], $client->fresh()->location_id);
    }

    public function test_client_profile_picture_can_be_changed(): void
    {
        Storage::fake('public');

        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAsAgency($user)
            ->post("/api/agency/clients/{$client->id}", [
                'image' => $file,
            ], ['Content-Type' => 'multipart/form-data']);

        $response->assertOk();

        $path = $client->fresh()->image;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_client_profile_update_rejects_duplicate_email(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        Client::create(['agency_id' => $agency->id, 'first_name' => 'Bob', 'email' => 'taken@example.com']);
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $this->actingAsAgency($user)
            ->postJson("/api/agency/clients/{$client->id}", ['email' => 'taken@example.com'])
            ->assertStatus(422);
    }

    public function test_client_lists_endpoint_flags_currently_assigned_options(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $nanny = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'client']);
        Type::create(['agency_id' => $agency->id, 'name' => 'Housekeeper', 'type' => 'client']);
        // A candidate-type Type must not leak into the client's "types" list.
        Type::create(['agency_id' => $agency->id, 'name' => 'Tutor', 'type' => 'candidate']);

        $miami = Location::create(['agency_id' => $agency->id, 'location' => 'Miami', 'status' => 1]);
        Location::create(['agency_id' => $agency->id, 'location' => 'New York', 'status' => 1]);

        $background = CheckList::create(['agency_id' => $agency->id, 'name' => 'Background Check', 'type' => 'client', 'status' => 1]);
        CheckList::create(['agency_id' => $agency->id, 'name' => 'Reference Check', 'type' => 'client', 'status' => 1]);

        $vip = Tag::create(['agency_id' => $agency->id, 'name' => 'VIP', 'type' => 'client', 'status' => 1]);
        Tag::create(['agency_id' => $agency->id, 'name' => 'Referral', 'type' => 'client', 'status' => 1]);

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Pristia',
            'email' => 'pristia@example.com',
            'type_id' => [$nanny->id],
            'location_id' => [$miami->id],
            'checklist_id' => [$background->id],
            'tag_id' => [$vip->id],
        ]);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}/lists");

        $response->assertOk()
            ->assertJsonCount(2, 'data.types')
            ->assertJsonFragment(['id' => $nanny->id, 'name' => 'Nanny', 'assigned' => true])
            ->assertJsonFragment(['id' => $background->id, 'name' => 'Background Check', 'assigned' => true])
            ->assertJsonFragment(['id' => $miami->id, 'name' => 'Miami', 'assigned' => true])
            ->assertJsonFragment(['id' => $vip->id, 'name' => 'VIP', 'assigned' => true]);

        $data = $response->json('data');
        $this->assertFalse(collect($data['types'])->firstWhere('name', 'Housekeeper')['assigned']);
    }

    public function test_client_lists_can_be_updated(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $nanny = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'client']);
        $chicago = Location::create(['agency_id' => $agency->id, 'location' => 'Chicago', 'status' => 1]);

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $response = $this->actingAsAgency($user)->patchJson("/api/agency/clients/{$client->id}/lists", [
            'type_id' => [$nanny->id],
            'location_id' => [$chicago->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type_id', [$nanny->id])
            ->assertJsonPath('data.location_id', [$chicago->id]);

        $this->assertSame([$nanny->id], $client->fresh()->type_id);
        $this->assertSame([$chicago->id], $client->fresh()->location_id);
    }

    public function test_client_lists_update_rejects_options_belonging_to_another_agency(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other.test', 'subdomain_prefix' => 'other', 'email' => fake()->unique()->safeEmail()]);

        $foreignType = Type::create(['agency_id' => $otherAgency->id, 'name' => 'Nanny', 'type' => 'client']);
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/lists", ['type_id' => [$foreignType->id]])
            ->assertStatus(422);
    }

    public function test_candidate_lists_endpoint_and_update_mirror_client_behavior(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $nanny = Type::create(['agency_id' => $agency->id, 'name' => 'Nanny', 'type' => 'candidate']);
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Kelsey', 'email' => 'kelsey@example.com']);

        $this->actingAsAgency($user)
            ->getJson("/api/agency/candidates/{$candidate->id}/lists")
            ->assertOk()
            ->assertJsonFragment(['id' => $nanny->id, 'name' => 'Nanny', 'assigned' => false]);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/candidates/{$candidate->id}/lists", ['type_id' => [$nanny->id]])
            ->assertOk()
            ->assertJsonPath('data.type_id', [$nanny->id]);
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

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
