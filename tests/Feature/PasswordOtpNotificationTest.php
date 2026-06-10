<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordOtpNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_send_otp_dispatches_password_otp_notification_to_user(): void
    {
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user = User::factory()->create([
            'agency_id' => $agency->id,
            'email' => 'reset-me@example.com',
        ]);

        $this
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/send-otp', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('password_otps', ['user_id' => $user->id]);

        Notification::assertSentTo(
            $user,
            PasswordOtpNotification::class,
            fn (PasswordOtpNotification $notification) => in_array('mail', $notification->via($user), true)
        );
    }

    public function test_send_otp_rejects_unknown_email(): void
    {
        Agency::create([
            'name' => 'Sarmeadors',
            'subdomain' => 'sarmeadors.test',
            'subdomain_prefix' => 'sarmeadors',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $this
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/send-otp', ['email' => 'nobody@example.com'])
            ->assertStatus(422);
    }
}
