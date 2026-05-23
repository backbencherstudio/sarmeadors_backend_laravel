<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ShortTermJob;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortTermJobController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();
        $agency = $request->current_agency;

        return Client::where('email', $user->email)
            ->where('agency_id', $agency->id)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (!$client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $status = $request->query('status');

            $query = ShortTermJob::with(['dates', 'children', 'location'])
                ->where('client_id', $client->id);

            if ($status) {
                $query->where('status', $status);
            }

            $jobs = $query->latest()->get();

            $counts = ShortTermJob::where('client_id', $client->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->sendResponse([
                'counts' => $counts,
                'jobs'   => $jobs,
            ], 'Jobs retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (!$client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $shortTermJob->load(['dates', 'children', 'location']);

            return $this->sendResponse($shortTermJob, 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (!$client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($shortTermJob->status === 'running') {
                return $this->sendError('Cannot delete a running job.', [], 422);
            }

            $shortTermJob->delete();

            return $this->sendResponse([], 'Job deleted successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $agency  = $request->current_agency;
            $client  = $this->resolveClient($request);

            if (!$client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $paymentRequired = $agency->short_term_payment_required
                && $agency->hasStripeKeys()
                && $agency->short_term_job_fee > 0;

            $validated = $request->validate([
                // Job Details
                'title'                    => 'required|string|max:255',
                'description'              => 'nullable|string',
                'cover_image'              => 'nullable|image|max:5120',

                // Children
                'children'                 => 'required|array|min:1',
                'children.*.first_name'    => 'required|string|max:255',
                'children.*.last_name'     => 'nullable|string|max:255',
                'children.*.date_of_birth' => 'nullable|date',
                'children.*.gender'        => 'nullable|in:male,female,other',
                'children.*.interests'     => 'nullable|string',
                'children.*.allergies'     => 'nullable|string',

                // Booking Dates
                'dates'                    => 'required|array|min:1',
                'dates.*.booking_date'     => 'required|date',
                'dates.*.start_time'       => 'required|date_format:H:i',
                'dates.*.end_time'         => 'required|date_format:H:i|after:dates.*.start_time',

                // Job Address
                'job_address'              => 'required|string|max:500',
                'home_city'                => 'required|string|max:100',
                'home_province'            => 'required|string|max:100',
                'home_postal_code'         => 'required|string|max:20',
                'country'                  => 'required|string|max:100',
                'location_id'              => 'nullable|integer|exists:locations,id',

                // Budget
                'compensation_amount'      => 'required|numeric|min:0',
                'compensation_currency'    => 'nullable|string|size:3',
                'compensation_type'        => 'nullable|in:per_hour,per_day,per_week,flat',

                // Payment — required only when agency has payment enabled
                'payment_method_id'        => ($paymentRequired ? 'required' : 'nullable') . '|string',
            ]);

            // Process payment first — job is only created after payment succeeds
            $stripePaymentIntentId = null;
            if ($paymentRequired) {
                $intent = $this->stripeService->createJobPaymentIntent(
                    $client,
                    $agency,
                    null,
                    (float) $agency->short_term_job_fee,
                    $agency->short_term_job_fee_currency
                );

                $confirmed = $this->stripeService->confirmPaymentIntent(
                    $agency,
                    $intent->id,
                    $validated['payment_method_id']
                );

                if ($confirmed->status !== 'succeeded') {
                    return $this->sendError('Payment failed. Status: ' . $confirmed->status, [], 422);
                }

                $stripePaymentIntentId = $confirmed->id;
            }

            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('jobs/covers', 'public');
            }

            $status = $agency->short_term_auto_approve ? 'marketplace' : 'pending_approval';

            $job = ShortTermJob::create([
                'agency_id'                => $agency->id,
                'client_id'                => $client->id,
                'location_id'              => $validated['location_id'] ?? null,
                'title'                    => $validated['title'],
                'description'              => $validated['description'] ?? null,
                'cover_image'              => $coverImagePath,
                'job_address'              => $validated['job_address'],
                'home_city'                => $validated['home_city'],
                'home_province'            => $validated['home_province'],
                'home_postal_code'         => $validated['home_postal_code'],
                'country'                  => $validated['country'],
                'compensation_amount'      => $validated['compensation_amount'],
                'compensation_currency'    => $validated['compensation_currency'] ?? 'usd',
                'compensation_type'        => $validated['compensation_type'] ?? 'per_hour',
                'status'                   => $status,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
            ]);

            foreach ($validated['dates'] as $date) {
                $job->dates()->create($date);
            }

            foreach ($validated['children'] as $child) {
                $job->children()->create($child);
            }

            $message = $agency->short_term_auto_approve
                ? 'Job created and listed on marketplace'
                : 'Job created and is pending approval';

            return $this->sendResponse(
                $job->load(['dates', 'children']),
                $message,
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
