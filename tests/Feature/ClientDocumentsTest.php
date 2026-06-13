<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientDocumentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_client_documents_index_lists_pending_and_signed_agreements(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        Carbon::setTestNow('2026-01-01 10:00:00');
        $pendingTemplate = $this->createTemplate($agency, 'Client - Agency Agreement Placement Fee & Refund Policy');

        Carbon::setTestNow('2026-01-02 10:00:00');
        $signedTemplate = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');

        Carbon::setTestNow();

        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $signedTemplate->id,
            'title' => $signedTemplate->name,
            'status' => 'signed',
            'signature' => 'Charlotte Hamlin',
            'signed_ip' => '103.89.241.15',
            'signed_at' => '2026-02-10 14:24:13',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/documents');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.agreements')
            ->assertJsonPath('data.agreements.1.id', $pendingTemplate->id)
            ->assertJsonPath('data.agreements.1.status', 'pending')
            ->assertJsonPath('data.agreements.1.description', 'Please review and sign this agreement.')
            ->assertJsonPath('data.agreements.1.can_sign', true)
            ->assertJsonPath('data.agreements.0.id', $signedTemplate->id)
            ->assertJsonPath('data.agreements.0.status', 'signed')
            ->assertJsonPath('data.agreements.0.description', "You've already signed this agreement.")
            ->assertJsonPath('data.agreements.0.can_sign', false)
            ->assertJsonPath('data.agreements.0.can_view', true);
    }

    public function test_client_can_view_agreement_with_audit_trail(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        Carbon::setTestNow('2025-11-29 09:24:13');
        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');
        Carbon::setTestNow();

        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'title' => $template->name,
            'status' => 'signed',
            'signature' => 'Charlotte Hamlin',
            'signed_ip' => '103.89.241.15',
            'signed_at' => '2025-12-02 19:24:09',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/documents/{$template->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.agreement.title', 'Client Agency Agreement Midwest Nannies')
            ->assertJsonPath('data.content_html', '<h1>Coast to Coast Nannies</h1><p>Agreement content</p>')
            ->assertJsonPath('data.organization.name', 'Coast to Coast Nannies')
            ->assertJsonPath('data.organization.signer_name', 'Sarah Meadors')
            ->assertJsonPath('data.client_signature.signature', 'Charlotte Hamlin')
            ->assertJsonPath('data.audit_trail.added_at_label', 'Sat Nov 29 2025 at 9:24:13 AM')
            ->assertJsonPath('data.audit_trail.signed_at_label', 'Tue Dec 02 2025 at 7:24:09 PM')
            ->assertJsonPath('data.audit_trail.ip', '103.89.241.15');
    }

    public function test_client_can_sign_agreement_once(): void
    {
        Carbon::setTestNow('2026-02-10 14:24:13');

        [$agency, $user, $client] = $this->createClientScenario();

        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'signed')
            ->assertJsonPath('data.signed_at', '2026-02-10 14:24:13')
            ->assertJsonPath('data.can_sign', false);

        $this->assertDatabaseHas('client_documents', [
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'status' => 'signed',
            'signature' => 'Charlotte Hamlin',
            'signed_ip' => '127.0.0.1',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already signed this agreement.');
    }

    public function test_signing_requires_signature_and_agreement(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed');
    }

    public function test_client_cannot_access_candidate_templates(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $candidateTemplate = DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Candidate Agreement',
            'user_type' => 'candidate',
            'content_type' => 'text',
            'content' => '<p>Candidate content</p>',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/documents/{$candidateTemplate->id}")
            ->assertNotFound();

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/documents')
            ->assertOk()
            ->assertJsonCount(0, 'data.agreements');
    }

    public function test_client_can_download_agreement_payload(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');

        ClientDocument::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'document_template_id' => $template->id,
            'title' => $template->name,
            'status' => 'signed',
            'signature' => 'Charlotte Hamlin',
            'signed_ip' => '103.89.241.15',
            'signed_at' => '2025-12-02 19:24:09',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/documents/{$template->id}/download");

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Client Agency Agreement Midwest Nannies')
            ->assertJsonPath('data.content_html', '<h1>Coast to Coast Nannies</h1><p>Agreement content</p>')
            ->assertJsonPath('data.signature', 'Charlotte Hamlin')
            ->assertJsonPath('data.signed_ip', '103.89.241.15');
    }

    public function test_signed_agreement_is_frozen_after_template_edit(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ])
            ->assertOk();

        $template->update(['content' => '<h1>Changed</h1><p>New terms the client never saw</p>']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/documents/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.content_html', '<h1>Coast to Coast Nannies</h1><p>Agreement content</p>');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/documents/{$template->id}/download")
            ->assertOk()
            ->assertJsonPath('data.content_html', '<h1>Coast to Coast Nannies</h1><p>Agreement content</p>');
    }

    public function test_pending_agreement_follows_live_template_content(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');
        $template->update(['content' => '<p>Updated terms</p>']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/documents/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.content_html', '<p>Updated terms</p>');
    }

    public function test_signed_record_survives_template_deletion(): void
    {
        [$agency, $user, $client] = $this->createClientScenario();

        $template = $this->createTemplate($agency, 'Client Agency Agreement Midwest Nannies');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ])
            ->assertOk();

        $template->delete();

        $this->assertDatabaseHas('client_documents', [
            'client_id' => $client->id,
            'document_template_id' => null,
            'status' => 'signed',
            'signature' => 'Charlotte Hamlin',
            'signed_content' => '<h1>Coast to Coast Nannies</h1><p>Agreement content</p>',
        ]);
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
        ]);

        Role::create([
            'name' => 'client',
            'guard_name' => 'web',
        ]);

        $user->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Charlotte',
            'last_name' => 'Hamlin',
            'email' => $user->email,
        ]);

        return [$agency, $user, $client];
    }

    private function createTemplate(Agency $agency, string $name): DocumentTemplate
    {
        return DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => $name,
            'user_type' => 'client',
            'content_type' => 'text',
            'content' => '<h1>Coast to Coast Nannies</h1><p>Agreement content</p>',
            'org_name' => 'Coast to Coast Nannies',
            'org_signer_name' => 'Sarah Meadors',
        ]);
    }
}
