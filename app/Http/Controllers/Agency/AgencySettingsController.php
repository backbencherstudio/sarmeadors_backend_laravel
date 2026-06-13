<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            $agency = $request->current_agency;
            $data = [
                'has_stripe_keys' => $agency->hasStripeKeys(),
                // Show masked key so agency knows what's saved
                'publishable_key_last4' => $agency->stripe_publishable_key
                    ? '...'.substr($agency->stripe_publishable_key, -8)
                    : null,
                'secret_key_last4' => $agency->stripe_secret_key
                    ? '...'.substr($agency->stripe_secret_key, -8)
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
                'stripe_secret_key' => 'required|string|starts_with:sk_',
            ]);

            $agency = $request->current_agency;

            // Save keys first (they get encrypted via model accessor)
            $agency->update([
                'stripe_publishable_key' => $request->stripe_publishable_key,
                'stripe_secret_key' => $request->stripe_secret_key,
            ]);

            // Validate the keys actually work
            $validation = $this->stripeService->validateKeys($agency);

            if (! $validation['valid']) {
                // Keys are invalid — remove them
                $agency->update([
                    'stripe_publishable_key' => null,
                    'stripe_secret_key' => null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Stripe keys. '.($validation['message'] ?? ''),
                ], 422);
            }

            $data = ['account_id' => $validation['account_id'] ?? null];

            return $this->sendResponse($data, 'Stripe keys saved and verified successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
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
            $agency = $request->current_agency;

            $agency->update([
                'stripe_publishable_key' => null,
                'stripe_secret_key' => null,
            ]);

            return $this->sendResponse([], 'Stripe keys removed.', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Get payment settings
     *
     * GET /api/agency/settings/payment
     */
    /**
     * Client-facing: check if payment is required before showing payment step
     *
     * GET /api/client/jobs/short-term/payment-check
     */
    public function clientShortTermPaymentCheck(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            $paymentRequired = (bool) $agency->short_term_payment_required
                && $agency->hasStripeKeys()
                && $agency->short_term_job_fee > 0;

            return $this->sendResponse([
                'payment_required' => $paymentRequired,
                'short_term_job_fee' => $paymentRequired ? $agency->short_term_job_fee : null,
                'short_term_job_fee_currency' => $paymentRequired ? $agency->short_term_job_fee_currency : null,
            ], 'success', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * GET /agency/settings/short-term-approval
     */
    public function getShortTermApprovalSettings(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            return $this->sendResponse([
                'short_term_auto_approve' => (bool) $agency->short_term_auto_approve,
            ], 'Approval settings retrieved', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * PUT /agency/settings/short-term-approval
     */
    public function updateShortTermApprovalSettings(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            $validated = $request->validate([
                'short_term_auto_approve' => 'required|boolean',
            ]);

            $agency->update([
                'short_term_auto_approve' => $validated['short_term_auto_approve'],
            ]);

            return $this->sendResponse([
                'short_term_auto_approve' => (bool) $agency->short_term_auto_approve,
            ], 'Approval settings updated', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function getShortTermFeeSettings(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            return $this->sendResponse([
                'short_term_payment_required' => (bool) $agency->short_term_payment_required,
                'short_term_job_fee' => $agency->short_term_job_fee,
                'short_term_job_fee_currency' => $agency->short_term_job_fee_currency,
                'has_stripe_keys' => $agency->hasStripeKeys(),
            ], 'Payment settings retrieved', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    /**
     * Update payment settings
     *
     * PUT /api/agency/settings/payment
     */
    public function updateShortTermFeeSettings(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;

            $validated = $request->validate([
                'short_term_payment_required' => 'required|boolean',
                'short_term_job_fee' => 'required_if:short_term_payment_required,true|numeric|min:0',
                'short_term_job_fee_currency' => 'nullable|string|size:3',
            ]);

            if ($validated['short_term_payment_required'] && ! $agency->hasStripeKeys()) {
                return $this->sendError(
                    'Cannot enable payment without Stripe keys configured.',
                    [],
                    422
                );
            }

            $agency->update([
                'short_term_payment_required' => $validated['short_term_payment_required'],
                'short_term_job_fee' => $validated['short_term_job_fee'] ?? 0,
                'short_term_job_fee_currency' => $validated['short_term_job_fee_currency'] ?? 'usd',
            ]);

            return $this->sendResponse([
                'short_term_payment_required' => (bool) $agency->short_term_payment_required,
                'short_term_job_fee' => $agency->short_term_job_fee,
                'short_term_job_fee_currency' => $agency->short_term_job_fee_currency,
            ], 'Payment settings updated', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
