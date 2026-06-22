<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientShortTermPaymentCheckTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_payment_check_returns_publishable_key_when_payment_required(): void
    {
        [$agency, $user] = $this->createClientScenario();

        $agency->update([
            'stripe_publishable_key' => 'pk_test_frontend_key',
            'stripe_secret_key' => 'sk_test_secret_key',
            'short_term_payment_required' => true,
            'short_term_job_fee' => 25,
            'short_term_job_fee_currency' => 'usd',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term/payment-check');

        $response
            ->assertOk()
            ->assertJsonPath('data.payment_required', true)
            ->assertJsonPath('data.payment_setup_incomplete', false)
            ->assertJsonPath('data.short_term_job_fee', '25.00')
            ->assertJsonPath('data.short_term_job_fee_currency', 'usd')
            ->assertJsonPath('data.stripe_publishable_key', 'pk_test_frontend_key');
    }

    public function test_stripe_keys_are_stored_as_plaintext(): void
    {
        [$agency] = $this->createClientScenario();

        $agency->update([
            'stripe_publishable_key' => 'pk_test_frontend_key',
            'stripe_secret_key' => 'sk_test_secret_key',
        ]);

        $raw = \DB::table('agencies')->where('id', $agency->id)->first();

        $this->assertSame('pk_test_frontend_key', $raw->stripe_publishable_key);
        $this->assertSame('sk_test_secret_key', $raw->stripe_secret_key);
        $this->assertSame('pk_test_frontend_key', $agency->fresh()->stripe_publishable_key);
    }

    public function test_payment_check_flags_incomplete_setup_when_fee_required_but_stripe_missing(): void
    {
        [$agency, $user] = $this->createClientScenario();

        $agency->update([
            'stripe_publishable_key' => null,
            'stripe_secret_key' => null,
            'short_term_payment_required' => true,
            'short_term_job_fee' => 25,
            'short_term_job_fee_currency' => 'usd',
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term/payment-check');

        $response
            ->assertOk()
            ->assertJsonPath('data.payment_required', false)
            ->assertJsonPath('data.payment_setup_incomplete', true)
            ->assertJsonPath('data.stripe_publishable_key', null);
    }

    public function test_payment_check_hides_publishable_key_when_payment_not_required(): void
    {
        [$agency, $user] = $this->createClientScenario();

        $agency->update([
            'stripe_publishable_key' => 'pk_test_frontend_key',
            'stripe_secret_key' => 'sk_test_secret_key',
            'short_term_payment_required' => false,
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->getJson('/api/client/jobs/short-term/payment-check');

        $response
            ->assertOk()
            ->assertJsonPath('data.payment_required', false)
            ->assertJsonPath('data.payment_setup_incomplete', false)
            ->assertJsonPath('data.stripe_publishable_key', null);
    }

    public function test_posting_short_term_job_is_blocked_when_payment_required_but_stripe_missing(): void
    {
        [$agency, $user] = $this->createClientScenario();

        $agency->update([
            'stripe_publishable_key' => null,
            'stripe_secret_key' => null,
            'short_term_payment_required' => true,
            'short_term_job_fee' => 25,
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->withHeader('X-Subdomain', 'sarmeadors')
            ->postJson('/api/client/jobs/short-term', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This agency requires payment to post a short-term job, but has not finished setting up payments yet. Please contact the agency.');
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
            'first_name' => 'Alex',
            'last_name' => 'Morrison',
            'email' => $user->email,
        ]);

        return [$agency, $user, $client];
    }
}
