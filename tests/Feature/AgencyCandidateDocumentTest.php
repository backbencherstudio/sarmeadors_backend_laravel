<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyCandidateDocumentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_all_document_sections(): void
    {
        [$agency, $admin, $candidate] = $this->createScenario();

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/documents");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['agreements', 'required_documents', 'additional_documents'],
            ])
            ->assertJsonCount(6, 'data.required_documents');
    }

    public function test_required_document_shows_missing_status_when_not_uploaded(): void
    {
        [$agency, $admin, $candidate] = $this->createScenario();

        $response = $this->actingAsAgency($admin)->getJson("/api/agency/candidates/{$candidate->id}/documents");

        $headshot = collect($response->json('data.required_documents'))->firstWhere('key', 'headshot');
        $this->assertSame('missing', $headshot['status']);
        $this->assertTrue($headshot['can_upload']);
        $this->assertFalse($headshot['can_replace']);
        $this->assertFalse($headshot['can_delete']);
    }

    public function test_admin_can_upload_a_required_document(): void
    {
        Storage::fake('public');
        [$agency, $admin, $candidate] = $this->createScenario();

        $response = $this->actingAsAgency($admin)->postJson(
            "/api/agency/candidates/{$candidate->id}/documents/required/government_id",
            ['file' => UploadedFile::fake()->create('id_card.pdf', 100, 'application/pdf')]
        );

        $response->assertOk()->assertJsonPath('data.status', 'uploaded');

        $this->assertDatabaseHas('candidate_documents', [
            'candidate_id' => $candidate->id,
            'required_key' => 'government_id',
            'category' => 'required',
            'status' => 'uploaded',
        ]);
    }

    public function test_uploading_required_document_replaces_existing_file(): void
    {
        Storage::fake('public');
        [$agency, $admin, $candidate] = $this->createScenario();

        $this->actingAsAgency($admin)->postJson(
            "/api/agency/candidates/{$candidate->id}/documents/required/headshot",
            ['file' => UploadedFile::fake()->image('photo1.jpg')]
        );

        $this->actingAsAgency($admin)->postJson(
            "/api/agency/candidates/{$candidate->id}/documents/required/headshot",
            ['file' => UploadedFile::fake()->image('photo2.jpg')]
        )->assertOk();

        $this->assertSame(1, CandidateDocument::where('candidate_id', $candidate->id)
            ->where('required_key', 'headshot')->count());
    }

    public function test_admin_can_delete_a_required_document(): void
    {
        Storage::fake('public');
        [$agency, $admin, $candidate] = $this->createScenario();

        $document = CandidateDocument::create([
            'agency_id' => $agency->id,
            'candidate_id' => $candidate->id,
            'required_key' => 'nanny_resume',
            'category' => 'required',
            'title' => 'Nanny Resume',
            'status' => 'uploaded',
        ]);

        $this->actingAsAgency($admin)
            ->deleteJson("/api/agency/candidates/{$candidate->id}/documents/{$document->id}")
            ->assertOk();

        $this->assertDatabaseMissing('candidate_documents', ['id' => $document->id]);
    }

    public function test_admin_can_upload_an_additional_document(): void
    {
        Storage::fake('public');
        [$agency, $admin, $candidate] = $this->createScenario();

        $response = $this->actingAsAgency($admin)->postJson(
            "/api/agency/candidates/{$candidate->id}/documents/additional",
            [
                'file' => UploadedFile::fake()->create('extra.pdf', 200, 'application/pdf'),
                'title' => 'Extra certificate',
            ]
        );

        $response->assertStatus(201)->assertJsonPath('data.title', 'Extra certificate');

        $this->assertDatabaseHas('candidate_documents', [
            'candidate_id' => $candidate->id,
            'category' => 'additional',
            'title' => 'Extra certificate',
        ]);
    }

    public function test_admin_can_update_an_additional_document(): void
    {
        [$agency, $admin, $candidate] = $this->createScenario();

        $document = CandidateDocument::create([
            'agency_id' => $agency->id,
            'candidate_id' => $candidate->id,
            'category' => 'additional',
            'title' => 'Old title',
            'status' => 'uploaded',
        ]);

        $this->actingAsAgency($admin)
            ->patchJson("/api/agency/candidates/{$candidate->id}/documents/additional/{$document->id}", [
                'title' => 'Updated title',
                'description' => 'A new description',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.description', 'A new description');

        $this->assertDatabaseHas('candidate_documents', [
            'id' => $document->id,
            'title' => 'Updated title',
            'description' => 'A new description',
        ]);
    }

    public function test_admin_can_update_additional_document_file(): void
    {
        Storage::fake('public');
        [$agency, $admin, $candidate] = $this->createScenario();

        $document = CandidateDocument::create([
            'agency_id' => $agency->id,
            'candidate_id' => $candidate->id,
            'category' => 'additional',
            'title' => 'My doc',
            'file_path' => 'candidate-documents/old.pdf',
            'original_file_name' => 'old.pdf',
            'status' => 'uploaded',
        ]);

        $this->actingAsAgency($admin)
            ->call('PATCH', "/api/agency/candidates/{$candidate->id}/documents/additional/{$document->id}", [], [], [
                'file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
            ], ['HTTP_X_SUBDOMAIN' => 'sarmeadors'])
            ->assertOk()
            ->assertJsonPath('data.file_name', 'new.pdf');

        $this->assertDatabaseHas('candidate_documents', [
            'id' => $document->id,
            'original_file_name' => 'new.pdf',
        ]);
    }

    public function test_unknown_required_document_key_returns_404(): void
    {
        Storage::fake('public');
        [$agency, $admin, $candidate] = $this->createScenario();

        $this->actingAsAgency($admin)
            ->postJson("/api/agency/candidates/{$candidate->id}/documents/required/nonexistent_key", [
                'file' => UploadedFile::fake()->create('file.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(404);
    }

    public function test_document_endpoints_are_scoped_to_the_authenticated_agency(): void
    {
        [$agency, $admin] = $this->createScenario();
        $otherAgency = Agency::create(['name' => 'Other', 'subdomain' => 'other.test', 'subdomain_prefix' => 'other', 'email' => fake()->unique()->safeEmail()]);
        $foreignCandidate = Candidate::create(['agency_id' => $otherAgency->id, 'first_name' => 'Foreign', 'email' => 'foreign@example.com']);

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/candidates/{$foreignCandidate->id}/documents")
            ->assertStatus(404);
    }

    private function createScenario(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $admin = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);

        foreach (['agency_admin', 'client', 'candidate'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
            Role::findOrCreate($roleName, 'api');
        }
        $admin->assignRole('agency_admin');

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Pristia',
            'email' => 'pristia@example.com',
        ]);

        return [$agency, $admin, $candidate];
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }
}
