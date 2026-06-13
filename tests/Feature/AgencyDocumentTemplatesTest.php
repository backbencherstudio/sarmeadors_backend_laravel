<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\DocumentTemplate;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgencyDocumentTemplatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_agency_can_create_template_with_fields_and_signers(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/agency/document-templates/store', [
                'name' => 'Background Check Form',
                'user_type' => 'client',
                'content_type' => 'text',
                'content' => '<p>By filling out this form you are giving rights to run a background check.</p>',
                'org_name' => 'Coast to Coast Nannies',
                'org_signer_name' => 'Sarah Meadors',
                'notification_emails' => json_encode(['admin@example.com']),
                'admin_view_ids' => json_encode([$user->id]),
                'fields' => json_encode([
                    [
                        'field_type' => 'text',
                        'field_label' => 'Drivers License Number',
                        'placeholder' => 'your number',
                        'is_required' => true,
                        'admin_only' => false,
                    ],
                    [
                        'field_type' => 'text',
                        'field_label' => 'Social Security Number',
                    ],
                ]),
                'signers' => json_encode([
                    ['signer_label' => 'Client'],
                ]),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Template Created Successfully');

        $template = DocumentTemplate::where('name', 'Background Check Form')->first();

        $this->assertNotNull($template);
        $this->assertSame($agency->id, $template->agency_id);
        $this->assertSame([$user->id], $template->admin_view_ids);

        $this->assertDatabaseHas('document_template_fields', [
            'document_template_id' => $template->id,
            'field_label' => 'Drivers License Number',
            'field_tag' => '[[field_1]]',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('document_template_signers', [
            'document_template_id' => $template->id,
            'signer_label' => 'Client',
        ]);
    }

    public function test_image_based_template_accepts_pdf_upload(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->post('/api/agency/document-templates/store', [
                'name' => 'Scanned Agreement',
                'user_type' => 'client',
                'content_type' => 'image',
                'image_file' => UploadedFile::fake()->create('agreement.pdf', 256, 'application/pdf'),
            ]);

        $response->assertCreated();

        $template = DocumentTemplate::where('name', 'Scanned Agreement')->first();

        $this->assertNotNull($template);
        $this->assertNotNull($template->file_path);

        if (File::exists(public_path($template->file_path))) {
            File::delete(public_path($template->file_path));
        }
    }

    public function test_agency_templates_index_supports_category_filters(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $category = Type::create([
            'agency_id' => $agency->id,
            'name' => 'Midwest Elite Nannies',
            'type' => 'document_category',
        ]);

        $this->createTemplate($agency, 'Client Agreement', 'client', $category->id);
        $this->createTemplate($agency, 'Candidate Agreement', 'candidate');

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/agency/document-templates?user_type=client&category_id='.$category->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Client Agreement');

        $categoryTypeResponse = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/agency/document-templates?category_type=document_category');

        $categoryTypeResponse
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Client Agreement');
    }

    public function test_agency_can_duplicate_template_with_fields_and_signers(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $template = $this->createTemplate($agency, 'Client Agreement', 'client');
        $template->fields()->create([
            'field_type' => 'text',
            'field_label' => 'Drivers License Number',
            'field_tag' => '[[field_1]]',
            'placeholder' => 'your number',
            'is_required' => true,
            'admin_only' => false,
        ]);
        $template->signers()->create(['signer_label' => 'Client']);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/agency/document-templates/duplicate/{$template->id}");

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Client Agreement (Copy)')
            ->assertJsonPath('data.fields.0.field_label', 'Drivers License Number')
            ->assertJsonPath('data.signers.0.signer_label', 'Client');

        $copyId = $response->json('data.id');

        $this->assertNotSame($template->id, $copyId);
        $this->assertDatabaseHas('document_templates', [
            'id' => $copyId,
            'agency_id' => $agency->id,
            'name' => 'Client Agreement (Copy)',
        ]);
    }

    public function test_agency_can_download_template_payload(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $template = $this->createTemplate($agency, 'Client Agreement', 'client');

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/agency/document-templates/download/{$template->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Client Agreement')
            ->assertJsonPath('data.content_html', '<p>Agreement content</p>')
            ->assertJsonPath('data.org_name', 'Coast to Coast Nannies');
    }

    public function test_agency_cannot_access_another_agencys_template(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $otherAgency = Agency::create([
            'name' => 'Other Agency',
            'subdomain' => 'other.test',
            'subdomain_prefix' => 'other',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $foreignTemplate = $this->createTemplate($otherAgency, 'Foreign Agreement', 'client');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/agency/document-templates/show/{$foreignTemplate->id}")
            ->assertNotFound();

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/agency/document-templates/duplicate/{$foreignTemplate->id}")
            ->assertNotFound();

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/agency/document-templates/delete/{$foreignTemplate->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('document_templates', [
            'id' => $foreignTemplate->id,
        ]);
    }

    public function test_agency_can_delete_own_template(): void
    {
        [$agency, $user] = $this->createAgencyScenario();

        $template = $this->createTemplate($agency, 'Client Agreement', 'client');

        $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/agency/document-templates/delete/{$template->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Template Deleted');

        $this->assertDatabaseMissing('document_templates', [
            'id' => $template->id,
        ]);
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

    private function createTemplate(Agency $agency, string $name, string $userType, ?int $categoryId = null): DocumentTemplate
    {
        return DocumentTemplate::create([
            'agency_id' => $agency->id,
            'name' => $name,
            'user_type' => $userType,
            'category_id' => $categoryId,
            'content_type' => 'text',
            'content' => '<p>Agreement content</p>',
            'org_name' => 'Coast to Coast Nannies',
            'org_signer_name' => 'Sarah Meadors',
        ]);
    }
}
