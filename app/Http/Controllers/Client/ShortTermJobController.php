<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ShortTermJob;
use App\Services\StripeService;
use App\Traits\FormatsJobPosting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ShortTermJobController extends Controller
{
    use FormatsJobPosting;

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

    /**
     * Translate the top-level `status` query param into the `filter[...]` shape
     * Spatie QueryBuilder expects, so the existing tab contract
     * (`?status=marketplace`) keeps working alongside native `?filter[status]=`.
     */
    private function jobFilterRequest(Request $request): Request
    {
        $filter = is_array($request->query('filter')) ? $request->query('filter') : [];

        if ($request->filled('status')) {
            $filter['status'] = $request->query('status');
        }

        return Request::create($request->url(), 'GET', ['filter' => $filter]);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $baseQuery = ShortTermJob::with(['dates', 'children', 'location', 'candidate', 'applications.candidate'])
                ->withCount([
                    'applications',
                    'applications as interviewed_count' => fn ($q) => $q->where('status', 'interviewed'),
                    'applications as hired_count' => fn ($q) => $q->where('status', 'hired'),
                ])
                ->where('client_id', $client->id);

            $jobs = QueryBuilder::for($baseQuery, $this->jobFilterRequest($request))
                ->allowedFilters(AllowedFilter::exact('status'))
                ->latest()
                ->get()
                ->map(fn (ShortTermJob $job): array => array_merge($this->formatJobCard($job), [
                    'applicants' => $this->formatApplicants($job),
                    'assigned_candidate' => $this->formatAssignedCandidate($job),
                    'schedule_summary' => $this->formatScheduleSummary($job),
                    'booking_dates' => $this->formatBookingDates($job),
                ]));

            $counts = ShortTermJob::where('client_id', $client->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return $this->sendResponse([
                'counts' => $counts,
                'jobs' => $jobs,
            ], 'Jobs retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function show(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $shortTermJob
                ->load(['dates', 'children', 'location', 'candidate', 'applications.candidate'])
                ->loadCount([
                    'applications',
                    'applications as interviewed_count' => fn ($q) => $q->where('status', 'interviewed'),
                    'applications as hired_count' => fn ($q) => $q->where('status', 'hired'),
                ]);

            $applicants = $this->formatApplicants($shortTermJob);

            // Applicant details stay behind the applicants endpoints; the
            // details payload only carries the summary block.
            $shortTermJob->unsetRelation('applications');

            return $this->sendResponse(array_merge($shortTermJob->toArray(), [
                'applicants' => $applicants,
                'assigned_candidate' => $this->formatAssignedCandidate($shortTermJob),
                'schedule_summary' => $this->formatScheduleSummary($shortTermJob),
                'booking_dates' => $this->formatBookingDates($shortTermJob),
            ]), 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if (! in_array($shortTermJob->status, ['pending_approval', 'rejected'])) {
                return $this->sendError('Only pending or rejected jobs can be edited.', [], 422);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'cover_image' => 'nullable|image|max:5120',
                'children' => 'sometimes|array|min:1',
                'children.*.first_name' => 'required_with:children|string|max:255',
                'children.*.last_name' => 'nullable|string|max:255',
                'children.*.date_of_birth' => 'nullable|date',
                'children.*.gender' => 'nullable|in:male,female,other',
                'children.*.interests' => 'nullable|string',
                'children.*.allergies' => 'nullable|string',
                'dates' => 'sometimes|array|min:1',
                'dates.*.booking_date' => 'required_with:dates|date',
                'dates.*.start_time' => 'required_with:dates|date_format:H:i',
                'dates.*.end_time' => 'required_with:dates|date_format:H:i|after:dates.*.start_time',
                'job_address' => 'sometimes|string|max:500',
                'home_city' => 'sometimes|string|max:100',
                'home_province' => 'sometimes|string|max:100',
                'home_postal_code' => 'sometimes|string|max:20',
                'country' => 'sometimes|string|max:100',
                'location_id' => 'nullable|integer|exists:locations,id',
                'compensation_amount' => 'sometimes|numeric|min:0',
                'compensation_currency' => 'nullable|string|size:3',
                'compensation_type' => 'nullable|in:per_hour,per_day,per_week,flat',
            ]);

            if ($request->hasFile('cover_image')) {
                $validated['cover_image'] = $request->file('cover_image')->store('jobs/covers', 'public');
            }

            // Resubmit: reset status to pending_approval
            if ($shortTermJob->status === 'rejected') {
                $validated['status'] = 'pending_approval';
                $validated['rejection_reason'] = null;
            }

            $shortTermJob->update($validated);

            if (! empty($validated['dates'])) {
                $shortTermJob->dates()->delete();
                foreach ($validated['dates'] as $date) {
                    $shortTermJob->dates()->create($date);
                }
            }

            if (! empty($validated['children'])) {
                $shortTermJob->children()->delete();
                foreach ($validated['children'] as $child) {
                    $shortTermJob->children()->create($child);
                }
            }

            return $this->sendResponse(
                $shortTermJob->load(['dates', 'children']),
                'Job updated successfully.',
                200
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function cancel(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $cancellableStatuses = ['pending_approval', 'marketplace', 'running'];
            if (! in_array($shortTermJob->status, $cancellableStatuses)) {
                return $this->sendError('This job cannot be cancelled in its current status.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:1000',
                'force_cancel' => 'nullable|boolean',
            ]);

            // 8-hour rule for running jobs
            $feeApplies = false;
            if ($shortTermJob->status === 'running') {
                $firstDate = $shortTermJob->dates()->orderBy('booking_date')->first();
                if ($firstDate) {
                    $startedAt = Carbon::parse($firstDate->booking_date.' '.$firstDate->start_time);
                    $hoursSinceStart = now()->diffInHours($startedAt, false);
                    if ($hoursSinceStart > 8 && empty($validated['force_cancel'])) {
                        return $this->sendResponse([
                            'fee_applies' => true,
                            'cancel_allowed' => true,
                        ], 'Cancellation time limit exceeded. Pass force_cancel=true to confirm.', 200);
                    }
                    $feeApplies = $hoursSinceStart > 8;
                }
            }

            $shortTermJob->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_at' => now(),
            ]);

            return $this->sendResponse(['fee_applies' => $feeApplies], 'Job cancelled successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function broadcastRequest(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($shortTermJob->status !== 'marketplace') {
                return $this->sendError('Broadcast is only available for approved marketplace jobs.', [], 422);
            }

            if ($shortTermJob->broadcast_requested) {
                return $this->sendError('Broadcast has already been requested.', [], 422);
            }

            $shortTermJob->update([
                'broadcast_requested' => true,
                'broadcast_requested_at' => now(),
            ]);

            return $this->sendResponse([], 'Broadcast request sent to admin.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
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

    // PUT /client/jobs/short-term/{shortTermJob}/resubmit
    public function resubmit(Request $request, ShortTermJob $shortTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $shortTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($shortTermJob->status !== 'rejected') {
                return $this->sendError('Only rejected jobs can be resubmitted.', [], 422);
            }

            $shortTermJob->update([
                'status' => 'pending_approval',
                'rejection_reason' => null,
            ]);

            return $this->sendResponse(
                $shortTermJob->fresh(),
                'Job resubmitted for approval.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;
            $client = $this->resolveClient($request);

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            // The agency requires a fee but hasn't connected Stripe: block the
            // post instead of silently creating a free job (which would lose the
            // agency money). The agency must finish payment setup first.
            if ($agency->shortTermPaymentIntended() && ! $agency->hasStripeKeys()) {
                return $this->sendError(
                    'This agency requires payment to post a short-term job, but has not finished setting up payments yet. Please contact the agency.',
                    [],
                    422
                );
            }

            $paymentRequired = $agency->shortTermPaymentActive();

            $validated = $request->validate([
                // Job Details
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'cover_image' => 'nullable|image|max:5120',

                // Children
                'children' => 'required|array|min:1',
                'children.*.first_name' => 'required|string|max:255',
                'children.*.last_name' => 'nullable|string|max:255',
                'children.*.date_of_birth' => 'nullable|date',
                'children.*.gender' => 'nullable|in:male,female,other',
                'children.*.interests' => 'nullable|string',
                'children.*.allergies' => 'nullable|string',

                // Booking Dates
                'dates' => 'required|array|min:1',
                'dates.*.booking_date' => 'required|date',
                'dates.*.start_time' => 'required|date_format:H:i',
                'dates.*.end_time' => 'required|date_format:H:i|after:dates.*.start_time',

                // Job Address
                'job_address' => 'required|string|max:500',
                'home_city' => 'required|string|max:100',
                'home_province' => 'required|string|max:100',
                'home_postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'location_id' => 'nullable|integer|exists:locations,id',

                // Budget
                'compensation_amount' => 'required|numeric|min:0',
                'compensation_currency' => 'nullable|string|size:3',
                'compensation_type' => 'nullable|in:per_hour,per_day,per_week,flat',

                // Payment — required only when agency has payment enabled
                'payment_method_id' => ($paymentRequired ? 'required' : 'nullable').'|string',
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
                    return $this->sendError('Payment failed. Status: '.$confirmed->status, [], 422);
                }

                $stripePaymentIntentId = $confirmed->id;
            }

            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('jobs/covers', 'public');
            }

            $status = $agency->short_term_auto_approve ? 'marketplace' : 'pending_approval';

            $job = ShortTermJob::create([
                'agency_id' => $agency->id,
                'client_id' => $client->id,
                'location_id' => $validated['location_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'cover_image' => $coverImagePath,
                'job_address' => $validated['job_address'],
                'home_city' => $validated['home_city'],
                'home_province' => $validated['home_province'],
                'home_postal_code' => $validated['home_postal_code'],
                'country' => $validated['country'],
                'compensation_amount' => $validated['compensation_amount'],
                'compensation_currency' => $validated['compensation_currency'] ?? 'usd',
                'compensation_type' => $validated['compensation_type'] ?? 'per_hour',
                'status' => $status,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
            ]);

            foreach ($validated['dates'] as $date) {
                $job->dates()->create($date);
            }

            foreach ($validated['children'] as $child) {
                $job->children()->create($child);
            }

            Payment::create([
                'agency_id' => $agency->id,
                'client_id' => $client->id,
                'short_term_job_id' => $job->id,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'amount' => $paymentRequired ? (float) $agency->short_term_job_fee : 0,
                'currency' => $agency->short_term_job_fee_currency ?? 'usd',
                'status' => $paymentRequired ? 'succeeded' : 'pending',
            ]);

            $message = $agency->short_term_auto_approve
                ? 'Job created and listed on marketplace'
                : 'Job created and is pending approval';

            return $this->sendResponse(
                $job->load(['dates', 'children']),
                $message,
                201
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
