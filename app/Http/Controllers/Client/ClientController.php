<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Http\Request;

class ClientController extends Controller
{

    public function __construct(
        private StripeService $stripeService
    ) {}

    public function index() {}

    public function store(Request $request)
    {
        try {
            $agency = $request->current_agency;

            $validated = $request->validate([
                'first_name'  => 'required|string|max:255',
                'last_name'   => 'nullable|string|max:255',
                'email'       => 'required|email|unique:clients,email',
                'mobile'      => 'nullable|string|max:20',
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'about_us'    => 'nullable|string',
            ]);

            if (!$agency->hasStripeKeys()) {
                return $this->sendError('This agency has not configured payment processing yet.', [], 422);
            }

            $exists = Client::where('agency_id', $agency->id)->where('email', $request->email)->exists();

            if ($exists) {
                return $this->sendError('Email already registered with this agency.', [], 422);
            }

            $amount = (float) $request->amount;
            $currency = $request->currency ?? 'usd';

            // dd($validated);
            $client = Client::create([
                'agency_id'     => $agency->id,
                'first_name'    => $validated['first_name'],
                'last_name'     => $validated['last_name'] ?? null,
                'email'         => $validated['email'],
                'mobile'        => $validated['mobile'] ?? null,
                'location_id'   => $validated['location_id'] ?? null,
                'about_us'      => $validated['about_us'] ?? null,
                'payment_status' => 'pending',
                'is_active'     => false,
            ]);

            $this->stripeService->createCustomer($client, $agency);

            $session = $this->stripeService->createCheckoutSession($client, $agency, $amount, $currency);

            Payment::create([
                'agency_id'                  => $agency->id,
                'client_id'                  => $client->id,
                'stripe_checkout_session_id' => $session->id,
                'amount'                     => $amount,
                'currency'                   => $currency,
                'status'                     => 'pending',
            ]);

            $data = [
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
                'client_id'    => $client->id,
            ];

            return $this->sendResponse($data, 'Complete payment to activate your account.', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Registration failed.', $e->getMessage(), 422);
        }
    }

    /**
     * Verify payment after Stripe redirect
     *
     * POST /api/client/verify-payment
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        // ★ Need agency to use their Stripe keys
        $agency = $request->current_agency;

        try {
            // Retrieve session using AGENCY's keys
            $session = $this->stripeService->retrieveCheckoutSession(
                $agency,
                $request->session_id
            );

            $payment = Payment::where('stripe_checkout_session_id', $session->id)
                ->where('agency_id', $agency->id)
                ->firstOrFail();

            if ($session->payment_status === 'paid') {

                $payment->update([
                    'status'                   => 'succeeded',
                    'stripe_payment_intent_id' => is_string($session->payment_intent)
                        ? $session->payment_intent
                        : $session->payment_intent->id,
                ]);

                $payment->client->update([
                    'payment_status' => 'paid',
                    'is_active'      => true,
                ]);

                return $this->sendResponse([],'Payment successful! Account activated.', 200);
            }

            return $this->sendError('Payment not completed.',('status' . $session->payment_status), 402);

        } catch (\Exception $e) {
            return $this->sendError('Could not verify payment.',$e->getMessage(), 500);
        }
    }
}
