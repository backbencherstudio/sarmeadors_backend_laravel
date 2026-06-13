<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobMessage;
use App\Models\LongTermJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MessagingInboxTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_client_inbox_lists_admin_and_candidate_conversations(): void
    {
        $scenario = $this->createScenario();

        JobMessage::create([
            'long_term_job_id' => $scenario['job']->id,
            'sender_id' => $scenario['adminUser']->id,
            'thread' => 'client',
            'message' => 'Sure! let me tell you about what we offer.',
        ]);

        JobMessage::create([
            'long_term_job_id' => $scenario['job']->id,
            'sender_id' => $scenario['candidateUser']->id,
            'thread' => 'client_candidate',
            'message' => 'Hello! Looking forward to meeting the kids.',
        ]);

        $adminTab = $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/messages');

        $adminTab
            ->assertOk()
            ->assertJsonPath('data.tab', 'admin')
            ->assertJsonPath('data.conversations.0.title', 'After School Nanny')
            ->assertJsonPath('data.conversations.0.counterpart.name', 'Sarmeadors')
            ->assertJsonPath('data.conversations.0.last_message.message', 'Sure! let me tell you about what we offer.')
            ->assertJsonPath('data.conversations.0.unread_count', 1)
            ->assertJsonPath('data.tabs.admin.unread', 1)
            ->assertJsonPath('data.tabs.candidate.unread', 1);

        $candidateTab = $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/messages?tab=candidate');

        $candidateTab
            ->assertOk()
            ->assertJsonPath('data.tab', 'candidate')
            ->assertJsonPath('data.conversations.0.counterpart.name', 'Darlene Robertson')
            ->assertJsonPath('data.conversations.0.last_message.message', 'Hello! Looking forward to meeting the kids.')
            ->assertJsonPath('data.conversations.0.unread_count', 1);
    }

    public function test_client_and_candidate_can_chat_directly(): void
    {
        $scenario = $this->createScenario();

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/jobs/long-term/{$scenario['job']->id}/candidate-messages", [
                'message' => 'Hi Darlene, can we talk about the schedule?',
            ])
            ->assertCreated();

        $candidateView = $this
            ->actingAs($scenario['candidateUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/candidate/jobs/long-term/{$scenario['job']->id}/client-messages");

        $candidateView
            ->assertOk()
            ->assertJsonPath('data.data.0.message', 'Hi Darlene, can we talk about the schedule?')
            ->assertJsonPath('data.data.0.thread', 'client_candidate');

        $this
            ->actingAs($scenario['candidateUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/candidate/jobs/long-term/{$scenario['job']->id}/client-messages", [
                'message' => 'Of course! I am free tomorrow.',
            ])
            ->assertCreated();

        $clientView = $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/jobs/long-term/{$scenario['job']->id}/candidate-messages");

        $clientView
            ->assertOk()
            ->assertJsonPath('data.data.1.message', 'Of course! I am free tomorrow.');
    }

    public function test_candidate_inbox_lists_client_conversations(): void
    {
        $scenario = $this->createScenario();

        JobMessage::create([
            'long_term_job_id' => $scenario['job']->id,
            'sender_id' => $scenario['clientUser']->id,
            'thread' => 'client_candidate',
            'message' => 'Hi! Are you available Friday?',
        ]);

        $response = $this
            ->actingAs($scenario['candidateUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/messages?tab=client');

        $response
            ->assertOk()
            ->assertJsonPath('data.tab', 'client')
            ->assertJsonPath('data.conversations.0.counterpart.name', 'Charlotte Hamlin')
            ->assertJsonPath('data.conversations.0.last_message.message', 'Hi! Are you available Friday?')
            ->assertJsonPath('data.tabs.client.unread', 1);
    }

    public function test_direct_thread_requires_assignment_and_ownership(): void
    {
        $scenario = $this->createScenario();

        $unassignedJob = $this->createJob($scenario['agency'], $scenario['client'], 'Unassigned Job');

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson("/api/client/jobs/long-term/{$unassignedJob->id}/candidate-messages")
            ->assertNotFound();

        $otherClient = Client::create([
            'agency_id' => $scenario['agency']->id,
            'first_name' => 'Other',
            'last_name' => 'Client',
            'email' => 'other-client@example.com',
        ]);

        $foreignJob = $this->createJob($scenario['agency'], $otherClient, 'Foreign Job', $scenario['candidate']);

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/jobs/long-term/{$foreignJob->id}/candidate-messages", [
                'message' => 'Should not work',
            ])
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function createScenario(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        foreach (['agency_admin', 'client', 'candidate'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $adminUser = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $adminUser->assignRole('agency_admin');

        $clientUser = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $clientUser->assignRole('client');

        $candidateUser = User::factory()->create(['agency_id' => $agency->id, 'email' => fake()->unique()->safeEmail()]);
        $candidateUser->assignRole('candidate');

        $client = Client::create([
            'agency_id' => $agency->id,
            'first_name' => 'Charlotte',
            'last_name' => 'Hamlin',
            'email' => $clientUser->email,
        ]);

        $candidate = Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Darlene',
            'last_name' => 'Robertson',
            'email' => $candidateUser->email,
        ]);

        $job = $this->createJob($agency, $client, 'After School Nanny', $candidate);

        return compact('agency', 'adminUser', 'clientUser', 'candidateUser', 'client', 'candidate', 'job');
    }

    private function createJob(Agency $agency, Client $client, string $title, ?Candidate $candidate = null): LongTermJob
    {
        return LongTermJob::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate?->id,
            'title' => $title,
            'description' => 'Full responsibility for three energetic children.',
            'job_address' => '26 Berkshire Ave.',
            'home_city' => 'Atlantic City',
            'home_province' => 'NJ',
            'home_postal_code' => '08401',
            'country' => 'USA',
            'start_date' => '2026-01-15',
            'compensation_amount' => 35,
            'compensation_currency' => 'usd',
            'compensation_type' => 'per_hour',
            'status' => $candidate ? 'filled' : 'marketplace',
        ]);
    }
}
