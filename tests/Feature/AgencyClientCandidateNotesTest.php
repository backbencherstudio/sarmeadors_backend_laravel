<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateNote;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyClientCandidateNotesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_lists_client_notes_pinned_first(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $older = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'Older']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $pinned = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'Pinned', 'is_pinned' => true]);
        $newest = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'Newest']);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}/notes");

        $response->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame($pinned->id, $response->json('data.0.id'));
        $this->assertSame($newest->id, $response->json('data.1.id'));
        $this->assertSame($older->id, $response->json('data.2.id'));
    }

    public function test_store_creates_a_client_note_and_notifies_selected_admins(): void
    {
        Notification::fake();

        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'last_name' => 'Candra', 'email' => 'pristia@example.com']);

        $otherAdmin = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $otherAdmin->assignRole('agency_admin');

        $response = $this->actingAsAgency($user)->postJson("/api/agency/clients/{$client->id}/notes", [
            'title' => 'Follow up',
            'body' => 'Call the client about contract renewal.',
            'notify_admin_ids' => [$otherAdmin->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Follow up')
            ->assertJsonPath('data.is_pinned', false)
            ->assertJsonPath('data.notify_admin_ids.0', $otherAdmin->id);

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id,
            'title' => 'Follow up',
            'user_id' => $user->id,
        ]);

        Notification::assertSentTo($otherAdmin, DatabaseNotification::class);
        Notification::assertNotSentTo($user, DatabaseNotification::class);
    }

    public function test_store_defaults_title_to_untitled_when_omitted(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $response = $this->actingAsAgency($user)->postJson("/api/agency/clients/{$client->id}/notes", []);

        $response->assertOk()->assertJsonPath('data.title', 'Untitled');
    }

    public function test_update_edits_a_note_and_can_trigger_new_notifications(): void
    {
        Notification::fake();

        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $note = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'Old title']);

        $otherAdmin = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $otherAdmin->assignRole('agency_admin');

        $response = $this->actingAsAgency($user)->patchJson("/api/agency/clients/{$client->id}/notes/{$note->id}", [
            'title' => 'New title',
            'notify_admin_ids' => [$otherAdmin->id],
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'New title');

        $this->assertDatabaseHas('client_notes', ['id' => $note->id, 'title' => 'New title']);
        Notification::assertSentTo($otherAdmin, DatabaseNotification::class);
    }

    public function test_toggle_pin_flips_state_each_time(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $note = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'Note']);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/notes/{$note->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/notes/{$note->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', false);
    }

    public function test_destroy_bulk_deletes_selected_notes(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $first = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'A']);
        $second = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'B']);
        $kept = ClientNote::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'user_id' => $user->id, 'title' => 'C']);

        $this->actingAsAgency($user)
            ->deleteJson("/api/agency/clients/{$client->id}/notes", ['ids' => [$first->id, $second->id]])
            ->assertOk();

        $this->assertDatabaseMissing('client_notes', ['id' => $first->id]);
        $this->assertDatabaseMissing('client_notes', ['id' => $second->id]);
        $this->assertDatabaseHas('client_notes', ['id' => $kept->id]);
    }

    public function test_available_admins_excludes_the_requesting_admin(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $otherAdmin = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $otherAdmin->assignRole('agency_admin');

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}/notes/admins");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $otherAdmin->id);
    }

    public function test_client_notes_endpoints_are_scoped_to_the_authenticated_agency(): void
    {
        [, $user] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other4.test', 'subdomain_prefix' => 'other4', 'email' => fake()->unique()->safeEmail()]);
        $foreignClient = Client::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($user)
            ->getJson("/api/agency/clients/{$foreignClient->id}/notes")
            ->assertStatus(404);
    }

    public function test_index_lists_candidate_notes_pinned_first(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $pinned = CandidateNote::create(['agency_id' => $agency->id, 'candidate_id' => $candidate->id, 'user_id' => $user->id, 'title' => 'Pinned', 'is_pinned' => true]);
        $newest = CandidateNote::create(['agency_id' => $agency->id, 'candidate_id' => $candidate->id, 'user_id' => $user->id, 'title' => 'Newest']);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/candidates/{$candidate->id}/notes");

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame($pinned->id, $response->json('data.0.id'));
        $this->assertSame($newest->id, $response->json('data.1.id'));
    }

    public function test_store_creates_a_candidate_note_and_notifies_selected_admins(): void
    {
        Notification::fake();

        [$agency, $user] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'last_name' => 'Lee', 'email' => 'jamie@example.com']);

        $otherAdmin = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $otherAdmin->assignRole('agency_admin');

        $response = $this->actingAsAgency($user)->postJson("/api/agency/candidates/{$candidate->id}/notes", [
            'title' => 'Background check',
            'notify_admin_ids' => [$otherAdmin->id],
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'Background check');

        $this->assertDatabaseHas('candidate_notes', [
            'candidate_id' => $candidate->id,
            'title' => 'Background check',
        ]);

        Notification::assertSentTo($otherAdmin, DatabaseNotification::class);
    }

    public function test_candidate_toggle_pin_flips_state_each_time(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);
        $note = CandidateNote::create(['agency_id' => $agency->id, 'candidate_id' => $candidate->id, 'user_id' => $user->id, 'title' => 'Note']);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/candidates/{$candidate->id}/notes/{$note->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);
    }

    public function test_candidate_destroy_bulk_deletes_selected_notes(): void
    {
        [$agency, $user] = $this->createAgencyScenario();
        $candidate = Candidate::create(['agency_id' => $agency->id, 'first_name' => 'Jamie', 'email' => 'jamie@example.com']);

        $first = CandidateNote::create(['agency_id' => $agency->id, 'candidate_id' => $candidate->id, 'user_id' => $user->id, 'title' => 'A']);
        $kept = CandidateNote::create(['agency_id' => $agency->id, 'candidate_id' => $candidate->id, 'user_id' => $user->id, 'title' => 'B']);

        $this->actingAsAgency($user)
            ->deleteJson("/api/agency/candidates/{$candidate->id}/notes", ['ids' => [$first->id]])
            ->assertOk();

        $this->assertDatabaseMissing('candidate_notes', ['id' => $first->id]);
        $this->assertDatabaseHas('candidate_notes', ['id' => $kept->id]);
    }

    public function test_candidate_notes_endpoints_are_scoped_to_the_authenticated_agency(): void
    {
        [, $user] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other5.test', 'subdomain_prefix' => 'other5', 'email' => fake()->unique()->safeEmail()]);
        $foreignCandidate = Candidate::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign-candidate@example.com']);

        $this->actingAsAgency($user)
            ->getJson("/api/agency/candidates/{$foreignCandidate->id}/notes")
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

        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        Role::findOrCreate('agency_admin', 'web');
        $user->assignRole('agency_admin');

        return [$agency, $user];
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
