<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateDocumentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_documents_index_returns_three_document_tabs(): void
    {
        [$agency, $user] = $this->createCandidateScenario();

        DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Client Agency Agreement Midwest Nannies',
            'user_type' => 'candidate',
            'content_type' => 'text',
            'content' => '<p>Agreement content</p>',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/documents');

        $response
            ->assertOk()
            ->assertJsonPath('data.agreements.0.title', 'Client Agency Agreement Midwest Nannies')
            ->assertJsonPath('data.agreements.0.status', 'pending')
            ->assertJsonPath('data.required_documents.0.key', 'headshot')
            ->assertJsonPath('data.required_documents.0.status', 'missing')
            ->assertJsonPath('data.additional_documents', []);
    }

    public function test_candidate_can_sign_agreement_and_upload_required_and_additional_documents(): void
    {
        Storage::fake('public');

        [$agency, $user] = $this->createCandidateScenario();

        $agreement = DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Client Agency Agreement Midwest Nannies',
            'user_type' => 'candidate',
            'content_type' => 'text',
            'content' => '<p>Agreement content</p>',
        ]);

        $signResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/documents/agreements/{$agreement->id}/sign", [
                'signature' => 'Alex Morrison',
                'agree' => true,
            ]);

        $signResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Client Agency Agreement Midwest Nannies')
            ->assertJsonPath('data.status', 'signed');

        $requiredResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/candidate/documents/required/headshot/upload', [
                'file' => UploadedFile::fake()->create('headshot.pdf', 128, 'application/pdf'),
            ]);

        $requiredResponse
            ->assertCreated()
            ->assertJsonPath('data.category', 'required')
            ->assertJsonPath('data.required_key', 'headshot')
            ->assertJsonPath('data.status', 'uploaded');

        $additionalResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/candidate/documents/additional', [
                'title' => 'Additional Letter(s) of recommendation',
                'file' => UploadedFile::fake()->create('recommendation.pdf', 128, 'application/pdf'),
            ]);

        $additionalResponse
            ->assertCreated()
            ->assertJsonPath('data.category', 'additional')
            ->assertJsonPath('data.title', 'Additional Letter(s) of recommendation')
            ->assertJsonPath('data.status', 'uploaded');
    }

    public function test_signed_agreement_is_frozen_and_cannot_be_resigned(): void
    {
        [$agency, $user] = $this->createCandidateScenario();

        $agreement = DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => 'Client Agency Agreement Midwest Nannies',
            'user_type' => 'candidate',
            'content_type' => 'text',
            'content' => '<p>Original agreement content</p>',
        ]);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/documents/agreements/{$agreement->id}/sign", [
                'signature' => 'Alex Morrison',
                'agree' => true,
            ])
            ->assertOk();

        $agreement->update(['content' => '<p>New terms the candidate never saw</p>']);

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/documents/agreements/{$agreement->id}")
            ->assertOk()
            ->assertJsonPath('data.content_html', '<p>Original agreement content</p>');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/documents/agreements/{$agreement->id}/sign", [
                'signature' => 'Alex Morrison',
                'agree' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already signed this agreement.');
    }

    private function createCandidateScenario(): array
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
            'name' => 'candidate',
            'guard_name' => 'web',
        ]);

        $user->assignRole('candidate');

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
        ]);

        return [$agency, $user, $candidate];
    }
}
