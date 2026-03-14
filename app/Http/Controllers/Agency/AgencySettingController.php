<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\Request;

class AgencySettingController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Get Stripe configuration status
     *
     * GET /api/agency/settings/stripe
     */
    public function getStripeStatus(Request $request)
    {
        try {
            $agency = $request->user();

            $data    = [
                'has_stripe_keys'    => $agency->hasStripeKeys(),
                'has_webhook_secret' => $agency->hasWebhookSecret(),
                'is_stripe_ready'    => $agency->isStripeReady(),

                // Masked keys so agency knows what's saved
                'publishable_key_last4' => $agency->stripe_publishable_key
                    ? '...' . substr($agency->stripe_publishable_key, -8)
                    : null,
                'secret_key_last4' => $agency->stripe_secret_key
                    ? '...' . substr($agency->stripe_secret_key, -8)
                    : null,
                'webhook_secret_last4' => $agency->stripe_webhook_secret
                    ? '...' . substr($agency->stripe_webhook_secret, -8)
                    : null,

                // Show the webhook URL they need to add in Stripe Dashboard
                'webhook_url' => url("/api/stripe/webhook/{$agency->subdomain_prefix}"),
            ];
            
            return $this->sendResponse($data, '', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 404);
        }
    }

    /**
     * Save Stripe keys + webhook secret
     *
     * POST /api/agency/settings/stripe
     */
    public function saveStripeKeys(Request $request)
    {
        try {
            $request->validate([
                'stripe_publishable_key' => 'required|string|starts_with:pk_',
                'stripe_secret_key' => 'required|string|starts_with:sk_',
                'stripe_webhook_secret' => 'required|string|starts_with:whsec_',
            ]);

            $agency = $request->user();

            $agency->update([
                'stripe_publishable_key' => $request->stripe_publishable_key,
                'stripe_secret_key' => $request->stripe_secret_key,
                'stripe_webhook_secret' => $request->stripe_secret_key,
            ]);

            $validation = $this->stripeService->validateKeys($agency);

            if (!$validation['valid']) {
                $agency->update([
                    'stripe_publishable_key' => null,
                    'stripe_secret_key' => null,
                    'stripe_webhook_secret' => null,
                ]);

                return $this->sendError('Validation failed', 'Invalid stripe keys.' . ($validation['message'] ?? ''), 422);
            }

            $data = [
                'account_id'  => $validation['account_id'] ?? null,
                'webhook_url' => url("/api/stripe/webhook/{$agency->subdomain_prefix}"),
            ];

            return $this->sendResponse($data, 'Stripe configuration saved successfully', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 404);
        }
    }

    /**
     * Remove all Stripe configuration
     *
     * DELETE /api/agency/settings/stripe
     */
    public function removeStripeKeys(Request $request)
    {
        try {
            $agency = $request->user();

            $agency->update([
                'stripe_publishable_key' => null,
                'stripe_secret_key'      => null,
                'stripe_webhook_secret'  => null,
            ]);

            return $this->sendResponse('', 'Stripe configuration removed.', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 404);
        }
    }
}
