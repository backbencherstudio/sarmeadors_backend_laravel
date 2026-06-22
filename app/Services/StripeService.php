<?php

namespace App\Services;

// app/Services/StripeService.php

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\ShortTermJob;
use Stripe\Checkout\Session;
use Stripe\Exception\AuthenticationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripeService
{
    /**
     * ★ Create a Stripe client using the AGENCY's secret key
     *   NOT the platform's key
     */
    private function getClient(Agency $agency): StripeClient
    {
        if (! $agency->hasStripeKeys()) {
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
                'valid' => true,
                'account_id' => $account->id,
                'email' => $account->email ?? null,
            ];
        } catch (AuthenticationException $e) {
            return [
                'valid' => false,
                'message' => 'Invalid Stripe secret key.',
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
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
            'name' => $client->full_name,
            'email' => $client->email,
            'phone' => $client->mobile,
            'metadata' => [
                'client_id' => $client->id,
                'agency_id' => $agency->id,
            ],
        ]);

        $client->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Return the client's Stripe customer id, creating the customer on the
     * agency's Stripe account the first time.
     */
    public function ensureCustomer(Client $client, Agency $agency): string
    {
        if ($client->stripe_customer_id) {
            return $client->stripe_customer_id;
        }

        return $this->createCustomer($client, $agency);
    }

    /**
     * Attach a payment method to the client's customer and persist the safe
     * display details so it can be reused for future bookings. The full card
     * number and CVC stay with Stripe — only brand/last4/expiry are stored.
     */
    public function storePaymentMethod(Client $client, Agency $agency, string $paymentMethodId, ?string $cardholderName = null): ClientPaymentMethod
    {
        $stripe = $this->getClient($agency);
        $customerId = $this->ensureCustomer($client, $agency);

        $stripe->paymentMethods->attach($paymentMethodId, ['customer' => $customerId]);

        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);
        $card = $paymentMethod->card ?? null;
        $isFirst = ClientPaymentMethod::where('client_id', $client->id)->doesntExist();

        return ClientPaymentMethod::updateOrCreate(
            [
                'client_id' => $client->id,
                'stripe_payment_method_id' => $paymentMethodId,
            ],
            [
                'agency_id' => $agency->id,
                'cardholder_name' => $cardholderName ?? ($paymentMethod->billing_details->name ?? null),
                'brand' => $card->brand ?? null,
                'last4' => $card->last4 ?? null,
                'exp_month' => $card->exp_month ?? null,
                'exp_year' => $card->exp_year ?? null,
                'is_default' => $isFirst,
            ]
        );
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
        float $amount,
        string $currency = 'usd'
    ): Session {

        $stripe = $this->getClient($agency);

        return $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $client->stripe_customer_id,
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) ($amount * 100),
                    'product_data' => [
                        'name' => 'Registration Fee',
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
                .'/registration/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url')
                .'/registration/cancel',
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
        Client $client,
        Agency $agency,
        ?ShortTermJob $job,
        float $amount,
        string $currency = 'usd',
        ?string $customerId = null,
        bool $savePaymentMethod = false
    ): PaymentIntent {

        $stripe = $this->getClient($agency);

        $params = [
            'amount' => (int) ($amount * 100),
            'currency' => strtolower($currency),
            'description' => 'Job Posting Fee'.($job ? ' – '.$job->title : ''),
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
            'metadata' => [
                'job_id' => $job?->id,
                'client_id' => $client->id,
                'agency_id' => $agency->id,
                'job_type' => 'short_term',
            ],
        ];

        if ($customerId) {
            $params['customer'] = $customerId;
        }

        if ($savePaymentMethod) {
            $params['setup_future_usage'] = 'off_session';
        }

        return $stripe->paymentIntents->create($params);
    }

    /**
     * Confirm a PaymentIntent on the backend using payment_method_id from frontend
     */
    public function confirmPaymentIntent(
        Agency $agency,
        string $paymentIntentId,
        string $paymentMethodId
    ): PaymentIntent {
        $stripe = $this->getClient($agency);

        return $stripe->paymentIntents->confirm($paymentIntentId, [
            'payment_method' => $paymentMethodId,
        ]);
    }

    /**
     * Retrieve a PaymentIntent using AGENCY's keys
     */
    public function retrievePaymentIntent(Agency $agency, string $paymentIntentId): PaymentIntent
    {
        $stripe = $this->getClient($agency);

        return $stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Retrieve checkout session using AGENCY's keys
     */
    public function retrieveCheckoutSession(Agency $agency, string $sessionId): Session
    {
        $stripe = $this->getClient($agency);

        return $stripe->checkout->sessions->retrieve(
            $sessionId,
            ['expand' => ['payment_intent']]
        );
    }
}
