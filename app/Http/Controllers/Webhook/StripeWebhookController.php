<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle webhook from a specific agency's Stripe account
     *
     * POST /api/stripe/webhook/{agencyPrefix}
     *
     * Each agency has their own:
     *   - Stripe API keys
     *   - Webhook secret (whsec_xxx)
     *   - Webhook endpoint URL
     */

    public function handle(Request $request)
    {
        try {
            $agency = $request->current_agency;

            if (!$agency) {
                return $this->sendError('Agency not found', [], 404);
            }

            if (!$agency->hasWebhookSecret()) {
                return $this->sendError('Webhook not configured', [], 400);
            }

            // --- Verify stripe signature ---

            $payload =  $request->getContent();
            $signature = $request->header('Stripe-Signature');

            if (!$signature) {
                return $this->sendError('Missing Signature',  [], 400);
            }

            try {
                $event = Webhook::constructEvent($payload, $signature, $agency->stripe_webhook_secret);
            } catch (SignatureVerificationException $e) {
                return $this->sendError('Invalid signature', $e->getMessage(), 400);
            } catch (\Exception $e) {
                return $this->sendError('Invalid payload', $e->getMessage(), 400);
            }

            // --Handle events-- //
            match ($event->type) {
                'checkout.session.completed'      => $this->onCheckoutComplete($event->data->object, $agency),
                'checkout.session.expired'        => $this->onCheckoutExpired($event->data->object, $agency),
                'payment_intent.succeeded'        => $this->onPaymentSucceeded($event->data->object, $agency),
                'payment_intent.payment_failed'   => $this->onPaymentFailed($event->data->object, $agency),
                // default                           => Log::info("Webhook [{$agencyPrefix}]: Unhandled event {$event->type}"),
            };

            return $this->sendResponse([], '', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 400);
        }
    }

    /**
     * Checkout completed — Activate client
     */
    private function onCheckoutComplete(object $session, Agency $agency)
    {
        try {
            $payment = Payment::where('stripe_checkout_session_id', $session->id)
                ->where('agency_id', $agency->id)
                ->first();
            if (!$payment) {
                return $this->sendError("No payment for session {$session->id} (Agency #{$agency->id})", [], 400);
            }

            if ($payment->status === 'succeeded') {
                return $this->sendResponse([], "Payment already processed {$session->id}", 200);
            }

            if ($payment->status === 'paid') {
                $payment->update([
                    'status'                   => 'succeeded',
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'metadata'                 => array_merge($payment->metadata ?? [], [
                        'completed_at' => now()->toISOString(),
                    ]),
                ]);

                $payment->client->update([
                    'payment_status' => 'paid',
                    'is_active'     => true,
                ]);
            }

            return $this->sendResponse([], "Client #{$payment->client_id} activated (Agency #{$agency->id})", 200);
        } catch (\Throwable $th) {
            return $this->sendError('Something went wrong', $th->getMessage(), 400);
        }
    }

    /**
     * Checkout expired — Client didn't complete payment
     */
    private function onCheckoutExpired(object $session, Agency $agency)
    {
        try {
            $payment = Payment::where('stripe_checkout_session_id', $session->id)
                ->where('agency_id', $agency->id)
                ->first();

            if (!$payment || $payment->status !== 'pending') return;

            $payment->update([
                'status'   => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'expired_at' => now()->toISOString(),
                ]),
            ]);

            $payment->client->update(['payment_status' => 'failed']);

            return $this->sendError("Checkout expired for Client #{$payment->client_id} (Agency #{$agency->id})" ,[], 402);
        } catch (\Throwable $th) {
            return $this->sendError('Something went wrong', $th->getMessage(), 400);
        }
    }

    /**
     * Payment intent succeeded (backup confirmation)
     */
    private function onPaymentSucceeded(object $paymentIntent, Agency $agency)
    {
        try {
            $clientId = $paymentIntent->metadata->client_id ?? null;
            if (!$clientId) return;

            $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)
                ->where('agency_id', $agency->id)
                ->first();

            if ($payment && $payment->status !== 'succeeded') {
                $payment->update(['status' => 'succeeded']);

                $payment->client->update([
                    'payment_status' => 'paid',
                    'is_active'      => true,
                ]);
            }

            return $this->sendResponse([], "Payment intent succeeded for Client #{$clientId} (Agency #{$agency->id})", 200);
        } catch (\Throwable $th) {
            return $this->sendError('Something went wrong', $th->getMessage(), 400);
        }
    }

    /**
     * Payment failed
     */
    private function onPaymentFailed(object $paymentIntent, Agency $agency)
    {
        try {
            $clientId = $paymentIntent->metadata->client_id ?? null;
            if (!$clientId) return;

            Payment::where('stripe_payment_intent_id', $paymentIntent->id)
                ->where('agency_id', $agency->id)
                ->update(['status' => 'failed']);

            Client::where('id', $clientId)
                ->where('agency_id', $agency->id)
                ->update(['payment_status' => 'failed']);

            $failReason = $paymentIntent->last_payment_error->message ?? 'Unknown';

            return $this->sendResponse([], "Payment failed for Client #{$clientId} (Agency #{$agency->id}): {$failReason}", 402);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
