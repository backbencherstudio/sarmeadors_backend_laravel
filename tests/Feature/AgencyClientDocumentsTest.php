<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyClientDocumentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_lists_all_client_templates_with_this_clients_status(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $unsigned = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement A', 'user_type' => 'client']);
        $signedTemplate = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement B', 'user_type' => 'client']);
        DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Candidate Only Doc', 'user_type' => 'candidate']);

        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $signedTemplate->id,
            'title' => 'Agreement B',
            'status' => 'signed',
            'signature' => 'Pristia Candra',
            'signed_at' => now(),
        ]);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}/documents");

        $response->assertOk()->assertJsonCount(2, 'data');

        $data = collect($response->json('data'));
        $pendingCard = $data->firstWhere('document_template_id', $unsigned->id);
        $signedCard = $data->firstWhere('document_template_id', $signedTemplate->id);

        $this->assertSame('pending', $pendingCard['status']);
        $this->assertTrue($pendingCard['is_active']);
        $this->assertNull($pendingCard['document_record_id']);

        $this->assertSame('signed', $signedCard['status']);
        $this->assertFalse($signedCard['can_edit']);
        $this->assertNotNull($signedCard['document_record_id']);
    }

    public function test_available_templates_excludes_already_tracked_ones(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);

        $tracked = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement A', 'user_type' => 'client']);
        $untracked = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement B', 'user_type' => 'client']);

        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $tracked->id,
            'title' => 'Agreement A',
            'status' => 'pending',
        ]);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}/documents/templates");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $untracked->id);
    }

    public function test_store_adds_a_template_to_the_client_as_pending_and_active(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement A', 'user_type' => 'client']);

        $response = $this->actingAsAgency($user)->postJson("/api/agency/clients/{$client->id}/documents", [
            'document_template_id' => $template->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('client_documents', [
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'status' => 'pending',
            'is_active' => 1,
        ]);
    }

    public function test_store_rejects_a_candidate_only_template(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Candidate Doc', 'user_type' => 'candidate']);

        $this->actingAsAgency($user)
            ->postJson("/api/agency/clients/{$client->id}/documents", ['document_template_id' => $template->id])
            ->assertStatus(422);
    }

    public function test_hitting_the_toggle_route_flips_the_active_state_each_time(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement A', 'user_type' => 'client']);

        // No ClientDocument row exists yet — hitting it the first time creates one, flipped to inactive.
        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/documents/{$template->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('client_documents', [
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'is_active' => 0,
        ]);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/documents/{$template->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_can_toggle_a_signed_document_too(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create(['agency_id' => $agency->id, 'name' => 'Agreement A', 'user_type' => 'client']);
        $document = ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'title' => 'Agreement A',
            'status' => 'signed',
            'signature' => 'Pristia Candra',
            'signed_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAsAgency($user)
            ->patchJson("/api/agency/clients/{$client->id}/documents/{$template->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'signed');

        $this->assertDatabaseHas('client_documents', ['id' => $document->id, 'status' => 'signed', 'is_active' => 0]);
    }

    public function test_admin_can_view_a_signed_documents_frozen_content(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Agreement A',
            'user_type' => 'client',
            'content' => 'Live template body (edited after signing)',
        ]);
        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'title' => 'Agreement A',
            'status' => 'signed',
            'signature' => 'Pristia Candra',
            'signed_content' => 'Frozen body at signing time',
            'signed_at' => now(),
        ]);

        $response = $this->actingAsAgency($user)->getJson("/api/agency/clients/{$client->id}/documents/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.content_html', 'Frozen body at signing time')
            ->assertJsonPath('data.signature', 'Pristia Candra');
    }

    public function test_admin_can_download_a_document_as_pdf(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'last_name' => 'Candra', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Agreement A',
            'user_type' => 'client',
            'content' => '<p>Body text</p>',
        ]);

        $response = $this->actingAsAgency($user)->get("/api/agency/clients/{$client->id}/documents/{$template->id}/download");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_download_a_signed_documents_frozen_content_as_pdf(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $client = Client::create(['agency_id' => $agency->id, 'first_name' => 'Pristia', 'email' => 'pristia@example.com']);
        $template = DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Agreement A',
            'user_type' => 'client',
            'content' => '<p>Live body (edited after signing)</p>',
        ]);
        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'title' => 'Agreement A',
            'status' => 'signed',
            'signature' => 'Pristia Candra',
            'signed_content' => '<p>Frozen body at signing time</p>',
            'signed_at' => now(),
        ]);

        $response = $this->actingAsAgency($user)->get("/api/agency/clients/{$client->id}/documents/{$template->id}/download");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_documents_endpoints_are_scoped_to_the_authenticated_agency(): void
    {
        [, $user] = $this->createAgencyScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other3.test', 'subdomain_prefix' => 'other3', 'email' => fake()->unique()->safeEmail()]);

        $foreignClient = Client::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($user)
            ->getJson("/api/agency/clients/{$foreignClient->id}/documents")
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

        Role::create(['name' => 'agency_admin', 'guard_name' => 'web']);
        $user->assignRole('agency_admin');

        return [$agency, $user];
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
