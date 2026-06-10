<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Candidate;
use App\Models\User;
use App\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CandidateNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_candidate_can_list_read_mark_all_and_delete_database_notifications(): void
    {
        $candidateUser = $this->createCandidateUser();

        $candidateUser->notify(new DatabaseNotification(
            type: 'job_posted',
            title: 'New Job Posted',
            body: 'A new nanny role is available in Manhattan.',
            actionUrl: '/candidate/jobs/long-term-marketplace',
            meta: ['job_type' => 'long_term'],
        ));

        $notification = $candidateUser->notifications()->firstOrFail();

        $listResponse = $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/candidate/notifications?filter=unread');

        $listResponse
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.data.0.id', $notification->id)
            ->assertJsonPath('data.notifications.data.0.type', 'job_posted')
            ->assertJsonPath('data.notifications.data.0.title', 'New Job Posted')
            ->assertJsonPath('data.notifications.data.0.body', 'A new nanny role is available in Manhattan.')
            ->assertJsonPath('data.notifications.data.0.action_url', '/candidate/jobs/long-term-marketplace')
            ->assertJsonPath('data.notifications.data.0.meta.job_type', 'long_term')
            ->assertJsonPath('data.notifications.data.0.is_read', false);

        $markReadResponse = $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson("/api/candidate/notifications/{$notification->id}/read");

        $markReadResponse
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $candidateUser->notify(new DatabaseNotification(
            type: 'interview_scheduled',
            title: 'Interview Scheduled',
            body: 'Your interview has been scheduled.',
        ));

        $markAllResponse = $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->putJson('/api/candidate/notifications/mark-all-read');

        $markAllResponse
            ->assertOk()
            ->assertJsonPath('data.marked', 1);

        $deleteResponse = $this
            ->actingAs($candidateUser, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->deleteJson("/api/candidate/notifications/{$notification->id}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    private function createCandidateUser(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        Role::create([
            'name' => 'candidate',
            'guard_name' => 'web',
        ]);

        $candidateUser = User::factory()->create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => 'alex@example.com',
        ]);
        $candidateUser->assignRole('candidate');

        Candidate::create([
            'agency_id' => $agency->id,
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $candidateUser->email,
        ]);

        return $candidateUser;
    }
}
