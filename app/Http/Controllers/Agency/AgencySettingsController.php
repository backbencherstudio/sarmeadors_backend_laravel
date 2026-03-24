<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AgencySettingsController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Get current Stripe configuration status
     *
     * GET /api/agency/settings/stripe
     */
    public function getStripeStatus(Request $request): JsonResponse
    {
        try {
            $agency = $request->user();
            $data = [
                'has_stripe_keys'      => $agency->hasStripeKeys(),
                // Show masked key so agency knows what's saved
                'publishable_key_last4' => $agency->stripe_publishable_key
                    ? '...' . substr($agency->stripe_publishable_key, -8)
                    : null,
                'secret_key_last4' => $agency->stripe_secret_key
                    ? '...' . substr($agency->stripe_secret_key, -8)
                    : null,
            ];

            return $this->sendResponse($data, 'success', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Save Stripe keys
     *
     * POST /api/agency/settings/stripe
     */
    public function saveStripeKeys(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'stripe_publishable_key' => 'required|string|starts_with:pk_',
                'stripe_secret_key'      => 'required|string|starts_with:sk_',
            ]);

            $agency = $request->user();

            // Save keys first (they get encrypted via model accessor)
            $agency->update([
                'stripe_publishable_key' => $request->stripe_publishable_key,
                'stripe_secret_key'      => $request->stripe_secret_key,
            ]);

            // Validate the keys actually work
            $validation = $this->stripeService->validateKeys($agency);

            if (!$validation['valid']) {
                // Keys are invalid — remove them
                $agency->update([
                    'stripe_publishable_key' => null,
                    'stripe_secret_key'      => null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Stripe keys. ' . ($validation['message'] ?? ''),
                ], 422);
            }

            $data  = ['account_id' => $validation['account_id'] ?? null,];

            return $this->sendResponse($data, 'Stripe keys saved and verified successfully.', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Remove Stripe keys
     *
     * DELETE /api/agency/settings/stripe
     */
    public function removeStripeKeys(Request $request): JsonResponse
    {
        try {
            $agency = $request->user();

            $agency->update([
                'stripe_publishable_key' => null,
                'stripe_secret_key'      => null,
            ]);

            return $this->sendResponse( [],'Stripe keys removed.', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
