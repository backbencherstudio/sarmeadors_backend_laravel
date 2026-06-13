<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\LongTermJob;
use App\Models\LongTermJobApplication;
use App\Models\LongTermJobInterview;
use App\Models\User;
use App\Notifications\AgreementSignedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_new_agreement_notifies_matching_portal_users(): void
    {
        $scenario = $this->createScenario();

        $this
            ->actingAs($scenario['adminUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/agency/document-templates/store', [
                'name' => 'Client Agreement',
                'user_type' => 'client',
                'content_type' => 'text',
                'content' => '<p>Agreement content</p>',
            ])
            ->assertCreated();

        $clientNotifications = $scenario['clientUser']->notifications()->get();
        $this->assertCount(1, $clientNotifications);
        $this->assertSame('new_agreement', $clientNotifications->first()->data['type']);
        $this->assertSame('Please review and sign "Client Agreement".', $clientNotifications->first()->data['body']);

        $this->assertCount(0, $scenario['candidateUser']->notifications()->get());
    }

    public function test_signing_agreement_notifies_agency_admins(): void
    {
        $scenario = $this->createScenario();

        $template = DocumentTemplate::create([
            'agency_id' => $scenario['agency']->id,
            'name' => 'Client Agency Agreement Midwest Nannies',
            'user_type' => 'client',
            'content_type' => 'text',
            'content' => '<p>Agreement content</p>',
        ]);

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ])
            ->assertOk();

        $adminNotifications = $scenario['adminUser']->notifications()->get();
        $this->assertCount(1, $adminNotifications);
        $this->assertSame('agreement_signed', $adminNotifications->first()->data['type']);
        $this->assertSame('Charlotte Hamlin signed "Client Agency Agreement Midwest Nannies".', $adminNotifications->first()->data['body']);
    }

    public function test_interview_reschedule_notifies_admins_and_candidate(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        $scenario = $this->createScenario();
        $interview = $this->createInterview($scenario);

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/client/interviews/{$interview->id}/reschedule", [
                'scheduled_date' => '2026-01-22',
                'available_from' => '14:00',
                'available_to' => '15:00',
                'reason' => 'Family emergency on the original date.',
            ])
            ->assertOk();

        $adminNotification = $scenario['adminUser']->notifications()->first();
        $this->assertNotNull($adminNotification);
        $this->assertSame('interview_rescheduled', $adminNotification->data['type']);
        $this->assertSame('Family emergency on the original date.', $adminNotification->data['meta']['reason']);

        $candidateNotification = $scenario['candidateUser']->notifications()->first();
        $this->assertNotNull($candidateNotification);
        $this->assertSame('interview_rescheduled', $candidateNotification->data['type']);
    }

    public function test_interview_cancel_notifies_admins_and_candidate(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        $scenario = $this->createScenario();
        $interview = $this->createInterview($scenario);

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/client/interviews/{$interview->id}")
            ->assertOk();

        $this->assertSame('interview_cancelled', $scenario['adminUser']->notifications()->first()?->data['type']);
        $this->assertSame('interview_cancelled', $scenario['candidateUser']->notifications()->first()?->data['type']);
    }

    public function test_signing_emails_the_template_notification_recipients(): void
    {
        Notification::fake();

        $scenario = $this->createScenario();

        $template = DocumentTemplate::create([
            'agency_id' => $scenario['agency']->id,
            'name' => 'Client Agency Agreement Midwest Nannies',
            'user_type' => 'client',
            'content_type' => 'text',
            'content' => '<p>Agreement content</p>',
            'notification_emails' => ['ops@example.com', 'owner@example.com'],
        ]);

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ])
            ->assertOk();

        Notification::assertSentOnDemand(
            AgreementSignedNotification::class,
            function (AgreementSignedNotification $notification, array $channels, object $notifiable): bool {
                return $notifiable->routes['mail'] === ['ops@example.com', 'owner@example.com']
                    && $notification->templateName === 'Client Agency Agreement Midwest Nannies'
                    && $notification->signerName === 'Charlotte Hamlin'
                    && $notification->signerType === 'client';
            }
        );
    }

    public function test_notification_feed_supports_groups_and_unread_filter(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        $scenario = $this->createScenario();

        $template = DocumentTemplate::create([
            'agency_id' => $scenario['agency']->id,
            'name' => 'Client Agreement',
            'user_type' => 'client',
            'content_type' => 'text',
            'content' => '<p>Agreement content</p>',
        ]);

        $this
            ->actingAs($scenario['clientUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson("/api/client/documents/{$template->id}/sign", [
                'signature' => 'Charlotte Hamlin',
                'agree' => true,
            ])
            ->assertOk();

        $scenario['adminUser']->notifications()->update(['created_at' => '2026-01-09 18:00:00']);

        $response = $this
            ->actingAs($scenario['adminUser'], 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/agency/notifications?filter=unread');

        $response
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.data.0.type', 'agreement_signed')
            ->assertJsonPath('data.notifications.data.0.group', 'Yesterday')
            ->assertJsonPath('data.notifications.data.0.is_read', false);
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

        return compact('agency', 'adminUser', 'clientUser', 'candidateUser', 'client', 'candidate');
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function createInterview(array $scenario): LongTermJobInterview
    {
        $job = LongTermJob::create([
            'agency_id' => $scenario['agency']->id,
            'client_id' => $scenario['client']->id,
            'title' => 'After School Nanny',
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
            'status' => 'marketplace',
        ]);

        $application = LongTermJobApplication::create([
            'agency_id' => $scenario['agency']->id,
            'long_term_job_id' => $job->id,
            'candidate_id' => $scenario['candidate']->id,
            'status' => 'interviewed',
        ]);

        return LongTermJobInterview::create([
            'agency_id' => $scenario['agency']->id,
            'long_term_job_id' => $job->id,
            'long_term_job_application_id' => $application->id,
            'candidate_id' => $scenario['candidate']->id,
            'scheduled_date' => '2026-01-18',
            'available_from' => '10:00',
            'available_to' => '11:00',
            'interview_type' => 'in_person',
            'status' => 'scheduled',
        ]);
    }
}
