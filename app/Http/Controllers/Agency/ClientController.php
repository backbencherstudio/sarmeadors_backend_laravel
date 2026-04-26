<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\FormSubmission;
use App\Models\FormFieldValue;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    // {
    // "form_id": 1,

    // "first_name": "John",
    // "last_name": "Doe",
    // "email": "john@example.com",
    // "mobile": "01700000000",

    // "type_id": [1, 2],
    // "location_id": [3],
    // "checklist_id": [5, 6],
    // "tag_id": [2],
    // "status_id": [1],

    // "fields": {
    //     "1": "Male",
    //     "2": "1996-10-10",
    // }
    // }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $form = Form::where('id', $request->form_id)
            ->where('agency_id', $agencyId)
            ->firstOrFail();

        $formFields = FormField::where('form_id', $form->id)
            ->where('status', 1)
            ->get();

        $rules = [
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'mobile' => 'nullable|string|max:20',
        ];

        foreach ($formFields as $field) {

            if ($field->validation_rules) {

                $rules["fields.$field->id"] = $field->validation_rules;
            }

            if ($field->is_required) {

                $rules["fields.$field->id"] = 'required';
            }
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {

            $client = Client::create([
                'agency_id' => $agencyId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'type_id' => $request->type_id,
                'location_id' => $request->location_id,
                'checklist_id' => $request->checklist_id,
                'tag_id' => $request->tag_id,
                'status_id' => $request->status_id
            ]);

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'entity_id' => $client->id
            ]);

            $allowedFields = $formFields->pluck('id')->toArray();

            $insertData = [];

            foreach ($request->fields ?? [] as $fieldId => $value) {

                if (!in_array($fieldId, $allowedFields)) {
                    continue;
                }

                $insertData[] = [
                    'submission_id' => $submission->id,
                    'form_field_id' => $fieldId,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (!empty($insertData)) {

                FormFieldValue::insert($insertData);
            }

            DB::commit();

            return $this->sendResponse([
                'status' => true,
                'message' => 'Client created successfully',
                'data' => $client
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $client = Client::with([
            'submissions.values.field'
        ])->findOrFail($id);

        return response()->json($client);
    }

    public function register(Request $request)
    {
        $agency = auth('api')->user()->agency;

        // New validations
        $rules = [
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'email'       => 'required|email|unique:clients,email',
            'mobile'      => 'nullable|string|max:20',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'about_us'    => 'nullable|string',
        ];

        // dd($request->all());
        // Stripe config check (NEW)
        if (!$agency->hasStripeKeys()) {
            return $this->sendError('This agency has not configured payment processing yet.', [], 422);
        }

        // Agency ভিত্তিক email check (UPDATED)
        $exists = Client::where('agency_id', $agency->id)
            ->where('email', $request->email)
            ->exists();

        if ($exists) {
            return $this->sendError('Email already registered with this agency.', [], 422);
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {

            // Payment related (NEW)
            $amount = (float) $request->amount;
            $currency = $request->currency ?? 'usd';

            // Updated client creation
            $client = Client::create([
                'agency_id'     => $agency->id,
                'first_name'    => $request->first_name,
                'last_name'     => $request->last_name ?? null,
                'email'         => $request->email,
                'password'      => $request->email,
                'must_change_password' => true, // Force password change on first login (NEW)
                'mobile'        => $request->mobile ?? null,
                'type_id'       => $request->type_id,
                'location_id'   => $request->location_id ?? null,
                'checklist_id'  => $request->checklist_id,
                'tag_id'        => $request->tag_id,


                'status_id'     => $request->status_id,
                'about_us'      => $request->about_us ?? null,
                'payment_status' => 'pending',
                'is_active'     => false,
            ]);

            // Stripe customer (NEW)
            $this->stripeService->createCustomer($client, $agency);

            // Stripe checkout session (NEW)
            $session = $this->stripeService->createCheckoutSession(
                $client,
                $agency,
                $amount,
                $currency
            );

            // Payment table (NEW)
            Payment::create([
                'agency_id'                  => $agency->id,
                'client_id'                  => $client->id,
                'stripe_checkout_session_id' => $session->id,
                'amount'                     => $amount,
                'currency'                   => $currency,
                'status'                     => 'pending',
            ]);

            DB::commit();

            return $this->sendResponse([
                'client'       => $client,
                'checkout_url' => $session->url,
                'session_id'   => $session->id
            ], 'Registration successful', 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return $this->sendError('Something went wrong', $e->getMessage(), 500);
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

        // dd($request->all());

        // ★ Need agency to use their Stripe keys
        $agency = auth('api')->user()->agency;

        try {
            // Retrieve session using AGENCY's keys
            $session = $this->stripeService->retrieveCheckoutSession(
                $agency,
                $request->session_id
            );

            // dd($session->payment_intent->id);

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

                return $this->sendResponse([], 'Payment successful! Account activated.', 200);
            }

            return $this->sendError('Payment not completed.', ('status' . $session->payment_status), 402);
        } catch (\Exception $e) {
            return $this->sendError('Could not verify payment.', $e->getMessage(), 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $agency = auth('api')->user()->agency;

            $client = Client::where('agency_id', $agency->id)
                ->where('email', $request->email)
                ->first();

            if (!$client) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            if (!$client->is_active) {
                return $this->sendError('Account inactive', [], 403);
            }

            $credentials = [
                'email' => $request->email,
                'password' => $request->password,
            ];

            if (!$token = auth('client')->attempt($credentials)) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            $client = auth('client')->user();

            return $this->sendResponse([
                'status' => true,
                'message' => 'Login successful',
                'token' => $token,
                'must_change_password' => $client->must_change_password,
                'data' => $client,
            ]);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function setPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string|min:6|confirmed',
            ]);

            $client = $request->user('client');

            if (!$client) {
                return $this->sendError('Unauthorized', [], 403);
            }

            if (!$request->user('client')->tokenCan('change-password')) {
                return $this->sendError('Invalid token for password change', [], 403);
            }

            if ($request->password === $client->email) {
                return $this->sendError('Password cannot be the same as email', [], 422);
            }

            $client->update([
                'password' => bcrypt($request->password),
                'must_change_password' => false,
            ]);

            $client->tokens()->delete();

            $token = $client->createToken('client-token', ['*'], now()->addDays(30))->plainTextToken;

            return $this->sendResponse([
                'token' => $token,
                'token_type' => 'Bearer',
            ], 'Password updated successfully', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function profile(Request $request)
    {
        try {
            $client = $request->user('client');

            if (!$client) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $data = [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'mobile' => $client->mobile,
                'about_us' => $client->about_us,
                'type_id' => $client->type_id,
                'location_id' => $client->location_id,
                'checklist_id' => $client->checklist_id,
                'tag_id' => $client->tag_id,
                'status_id' => $client->status_id,
                'payment_status' => $client->payment_status,
                'is_active' => $client->is_active,
            ];

            return $this->sendResponse($data, 'Client profile retrieved successfully', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            $client = $request->user('client');

            if (!$client) {
                return $this->sendError('Unauthorized', [], 403);
            }

            if (!Hash::check($request->current_password, $client->password)) {
                return $this->sendError('Current password is incorrect', [], 422);
            }

            if ($request->new_password === $client->email) {
                return $this->sendError('New password cannot be the same as email', [], 422);
            }

            $client->update([
                'password' => bcrypt($request->new_password),
                'must_change_password' => false,
            ]);

            // Revoke all tokens after password change
            $client->tokens()->delete();

            return $this->sendResponse([], 'Password changed successfully', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $client = $request->user('client');

            if ($client) {
                $client->tokens()->delete();
            }

            return $this->sendResponse([], 'Logged out successfully', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
