<?php

namespace App\Services;

// app/Services/StripeService.php

use App\Models\Agency;
use App\Models\Client;
use App\Models\ShortTermJob;
use Stripe\StripeClient;

class StripeService
{
    /**
     * ★ Create a Stripe client using the AGENCY's secret key
     *   NOT the platform's key
     */
    private function getClient(Agency $agency): StripeClient
    {
        if (!$agency->hasStripeKeys()) {
            throw new \Exception('Agency has not configured Stripe keys.');
        }

        return new StripeClient($agency->stripe_secret_key);
    }

    /**
     * Validate that the agency's Stripe keys are working
     */
    public function validateKeys(Agency $agency): array
    {
        try {
            $stripe = $this->getClient($agency);

            // Try a simple API call to verify the key works
            $account = $stripe->accounts->retrieve('self');

            return [
                'valid'      => true,
                'account_id' => $account->id,
                'email'      => $account->email ?? null,
            ];
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return [
                'valid'   => false,
                'message' => 'Invalid Stripe secret key.',
            ];
        } catch (\Exception $e) {
            return [
                'valid'   => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Stripe Customer on AGENCY's Stripe account
     */
    public function createCustomer(Client $client, Agency $agency): string
    {
        $stripe = $this->getClient($agency);

        $customer = $stripe->customers->create([
            'name'  => $client->full_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'metadata' => [
                'client_id' => $client->id,
                'agency_id' => $agency->id,
            ],
        ]);

        $client->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Create Checkout Session on AGENCY's Stripe account
     *
     * ★ All money goes directly to the agency
     *   because we're using THEIR Stripe keys
     */
    public function createCheckoutSession(
        Client $client,
        Agency $agency,
        float  $amount,
        string $currency = 'usd'
    ): \Stripe\Checkout\Session {

        $stripe = $this->getClient($agency);

        return $stripe->checkout->sessions->create([
            'mode'       => 'payment',
            'customer'   => $client->stripe_customer_id,
            'line_items' => [[
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => (int)($amount * 100),
                    'product_data' => [
                        'name' => "Registration Fee",
                    ],
                ],
                'quantity' => 1,
            ]],
            'payment_intent_data' => [
                'metadata' => [
                    'client_id' => $client->id,
                    'agency_id' => $agency->id,
                ],
            ],
            'success_url' => config('app.frontend_url')
                . "/registration/success?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url'  => config('app.frontend_url')
                . "/registration/cancel",
            'metadata' => [
                'client_id' => $client->id,
                'agency_id' => $agency->id,
            ],
        ]);
    }

    /**
     * Create a PaymentIntent for job posting fee — returns client_secret for custom form
     */
    public function createJobPaymentIntent(
        Client        $client,
        Agency        $agency,
        ?ShortTermJob $job,
        float         $amount,
        string        $currency = 'usd'
    ): \Stripe\PaymentIntent {

        $stripe = $this->getClient($agency);

        return $stripe->paymentIntents->create([
            'amount'      => (int) ($amount * 100),
            'currency'    => strtolower($currency),
            'description' => 'Job Posting Fee' . ($job ? ' – ' . $job->title : ''),
            'metadata'    => [
                'job_id'    => $job?->id,
                'client_id' => $client->id,
                'agency_id' => $agency->id,
                'job_type'  => 'short_term',
            ],
        ]);
    }

    /**
     * Confirm a PaymentIntent on the backend using payment_method_id from frontend
     */
    public function confirmPaymentIntent(
        Agency $agency,
        string $paymentIntentId,
        string $paymentMethodId
    ): \Stripe\PaymentIntent {
        $stripe = $this->getClient($agency);

        return $stripe->paymentIntents->confirm($paymentIntentId, [
            'payment_method' => $paymentMethodId,
        ]);
    }

    /**
     * Retrieve a PaymentIntent using AGENCY's keys
     */
    public function retrievePaymentIntent(Agency $agency, string $paymentIntentId): \Stripe\PaymentIntent
    {
        $stripe = $this->getClient($agency);

        return $stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Retrieve checkout session using AGENCY's keys
     */
    public function retrieveCheckoutSession(Agency $agency, string $sessionId): \Stripe\Checkout\Session
    {
        $stripe = $this->getClient($agency);

        return $stripe->checkout->sessions->retrieve(
            $sessionId,
            ['expand' => ['payment_intent']]
        );
    }
}