<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Form;
use App\Models\LongTermJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientLongTermJobFormSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_can_list_enabled_long_term_job_forms(): void
    {
        [$agency, $clientUser] = $this->createClientScenario();

        Form::create([
            'agency_id' => $agency->id,
            'name' => 'Post Long-term Job',
            'slug' => 'post-long-term-job',
            'entity' => 'long_term_job',
            'application_type' => 'job_posting',
            'job_type' => 'long_term',
            'schema' => ['blocks' => []],
            'status' => true,
        ]);

        Form::create([
            'agency_id' => $agency->id,
            'name' => 'Disabled Form',
            'slug' => 'disabled-form',
            'entity' => 'long_term_job',
            'application_type' => 'job_posting',
            'job_type' => 'long_term',
            'schema' => ['blocks' => []],
            'status' => false,
        ]);

        $this->actingAsClient($clientUser)
            ->getJson('/api/client/forms')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'post-long-term-job');
    }

    public function test_client_can_submit_long_term_job_form_as_pending_approval(): void
    {
        [$agency, $clientUser, $client] = $this->createClientScenario();

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Post Long-term Job',
            'slug' => 'post-long-term-job',
            'entity' => 'long_term_job',
            'application_type' => 'job_posting',
            'job_type' => 'long_term',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Job Details',
                    'type' => 'standard',
                    'service_id' => null,
                    'sections' => [[
                        'name' => 'Basic',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Title', 'name' => 'title', 'is_required' => true],
                            ['type' => 'text_area', 'label' => 'Description', 'name' => 'description'],
                        ],
                    ]],
                ]],
            ],
        ]);

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/client/forms/{$form->slug}/submit", [
                'answers' => [
                    'title' => 'Live-in Nanny',
                    'description' => 'Full time care needed',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entity', 'long_term_job')
            ->assertJsonPath('data.status', 'pending_approval');

        $this->assertDatabaseHas('long_term_jobs', [
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Live-in Nanny',
            'status' => 'pending_approval',
        ]);

        $job = LongTermJob::where('title', 'Live-in Nanny')->first();

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => $form->id,
            'entity_id' => $job->id,
            'entity_type' => 'long_term_job',
        ]);
    }

    public function test_agency_can_approve_and_reject_pending_form_job(): void
    {
        [$agency, $clientUser, $client] = $this->createClientScenario();
        $admin = $this->createAgencyAdmin($agency);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Post Long-term Job',
            'slug' => 'post-long-term-job',
            'entity' => 'long_term_job',
            'application_type' => 'job_posting',
            'job_type' => 'long_term',
            'status' => true,
            'schema' => [
                'blocks' => [[
                    'name' => 'Job Details',
                    'sections' => [[
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Title', 'name' => 'title', 'is_required' => true],
                        ],
                    ]],
                ]],
            ],
        ]);

        $jobId = $this->actingAsClient($clientUser)
            ->postJson("/api/client/forms/{$form->slug}/submit", [
                'answers' => ['title' => 'Weekend Nanny'],
            ])
            ->json('data.record.id');

        $this->actingAsAgency($admin)
            ->putJson("/api/agency/jobs/long-term/{$jobId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'marketplace');

        $jobId2 = $this->actingAsClient($clientUser)
            ->postJson("/api/client/forms/{$form->slug}/submit", [
                'answers' => ['title' => 'Another Job'],
            ])
            ->json('data.record.id');

        $this->actingAsAgency($admin)
            ->putJson("/api/agency/jobs/long-term/{$jobId2}/reject", [
                'reason' => 'Incomplete address details',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Incomplete address details');

        $this->actingAsAgency($admin)
            ->putJson("/api/agency/jobs/long-term/{$jobId2}/reject", [])
            ->assertStatus(422);
    }

    public function test_agency_sees_requested_jobs_as_cards_and_block_details(): void
    {
        [$agency, $clientUser, $client] = $this->createClientScenario();
        $admin = $this->createAgencyAdmin($agency);

        $client->update([
            'first_name' => 'Jerome',
            'last_name' => 'Bell',
            'mobile' => '+17036258009',
        ]);

        $form = Form::create([
            'agency_id' => $agency->id,
            'name' => 'Post Long-term Job',
            'slug' => 'post-long-term-job',
            'entity' => 'long_term_job',
            'application_type' => 'job_posting',
            'job_type' => 'long_term',
            'status' => true,
            'schema' => [
                'blocks' => [
                    [
                        'name' => 'Contact & Address',
                        'type' => 'standard',
                        'service_id' => null,
                        'sections' => [[
                            'name' => 'Parent Information',
                            'fields' => [
                                ['type' => 'text_box', 'label' => 'Title', 'name' => 'title', 'is_required' => true],
                                ['type' => 'text_area', 'label' => 'Description', 'name' => 'description'],
                                ['type' => 'text_box', 'label' => 'Location', 'name' => 'location'],
                            ],
                        ]],
                    ],
                    [
                        'name' => 'Requirements',
                        'type' => 'standard',
                        'service_id' => null,
                        'sections' => [[
                            'name' => 'Questions',
                            'fields' => [
                                ['type' => 'radio_button', 'label' => 'Need after school?', 'name' => 'after_school', 'options' => ['Yes', 'No']],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        $jobId = $this->actingAsClient($clientUser)
            ->postJson("/api/client/forms/{$form->slug}/submit", [
                'answers' => [
                    'title' => 'After School Nanny',
                    'description' => 'Full responsibility for three energetic children',
                    'location' => '71 Raglan Street, CROWNTHORPE',
                    'after_school' => 'Yes',
                ],
            ])
            ->json('data.record.id');

        $this->actingAsAgency($admin)
            ->getJson('/api/agency/jobs/long-term?status=pending_approval&search=Jerome')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.jobs.0.id', $jobId)
            ->assertJsonPath('data.jobs.0.title', 'After School Nanny')
            ->assertJsonPath('data.jobs.0.client.name', 'Jerome Bell')
            ->assertJsonPath('data.jobs.0.actions.can_publish', true)
            ->assertJsonPath('data.jobs.0.actions.can_reject', true);

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/jobs/long-term/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.job.status', 'pending_approval')
            ->assertJsonPath('data.client.name', 'Jerome Bell')
            ->assertJsonPath('data.block_tabs.0.slug', 'contact-address')
            ->assertJsonPath('data.block_tabs.1.slug', 'requirements')
            ->assertJsonPath('data.blocks.0.name', 'Contact & Address')
            ->assertJsonPath('data.blocks.0.sections.0.fields.0.value', 'After School Nanny')
            ->assertJsonPath('data.blocks.1.sections.0.fields.0.value', 'Yes');

        $this->actingAsAgency($admin)
            ->getJson("/api/agency/jobs/long-term/{$jobId}?block=requirements")
            ->assertOk()
            ->assertJsonCount(1, 'data.blocks')
            ->assertJsonPath('data.blocks.0.slug', 'requirements');
    }

    private function actingAsClient(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }

    private function actingAsAgency(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('X-Subdomain', 'sarmeadors');
    }

    /**
     * @return array{0: Agency, 1: User, 2: Client}
     */
    private function createClientScenario(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);
        $user->assignRole('client');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Parent',
            'email' => $user->email,
        ]);

        return [$agency, $user, $client];
    }

    private function createAgencyAdmin(Agency $agency): User
    {
        Role::firstOrCreate(['name' => 'agency_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => fake()->unique()->safeEmail(),
        ]);
        $admin->assignRole('agency_admin');

        return $admin;
    }
}
