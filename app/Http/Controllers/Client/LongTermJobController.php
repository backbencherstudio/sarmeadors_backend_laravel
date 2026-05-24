<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LongTermJobController extends Controller
{
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

            if (! $client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $status = $request->query('status');

            $query = LongTermJob::with(['schedules', 'children', 'location', 'latestAttendance'])
                ->withCount([
                    'applications',
                    'applications as interviewed_count' => fn ($q) => $q->where('status', 'interviewed'),
                    'applications as hired_count' => fn ($q) => $q->where('status', 'hired'),
                ])
                ->where('client_id', $client->id);

            if ($status) {
                $query->where('status', $status);
            }

            $jobs = $query->latest()->get();

            $counts = LongTermJob::where('client_id', $client->id)
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

    public function show(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $longTermJob->load(['schedules', 'children', 'location', 'candidate']);

            return $this->sendResponse($longTermJob, 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /client/jobs/long-term/{longTermJob}/cancel
    public function cancel(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $cancellableStatuses = ['pending_approval', 'marketplace', 'running'];
            if (! in_array($longTermJob->status, $cancellableStatuses)) {
                return $this->sendError('This job cannot be cancelled in its current status.', [], 422);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:1000',
                'force_cancel' => 'nullable|boolean', // true = client accepts the fee
            ]);

            // 8-hour rule: if running and started more than 8 hours ago, warn unless forced
            $feeApplies = false;
            if ($longTermJob->status === 'running' && $longTermJob->cancelled_at === null) {
                $hoursSinceStart = now()->diffInHours($longTermJob->start_date, false);
                if ($hoursSinceStart > 8 && empty($validated['force_cancel'])) {
                    return $this->sendResponse([
                        'fee_applies' => true,
                        'cancel_allowed' => true,
                    ], 'Cancellation time limit exceeded. Pass force_cancel=true to confirm.', 200);
                }
                $feeApplies = $hoursSinceStart > 8;
            }

            $longTermJob->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_at' => now(),
            ]);

            return $this->sendResponse([
                'fee_applies' => $feeApplies,
            ], 'Job cancelled successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /client/jobs/long-term/{longTermJob}/broadcast
    public function broadcastRequest(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status !== 'marketplace') {
                return $this->sendError('Broadcast is only available for approved marketplace jobs.', [], 422);
            }

            if ($longTermJob->broadcast_requested) {
                return $this->sendError('Broadcast has already been requested.', [], 422);
            }

            $longTermJob->update([
                'broadcast_requested' => true,
                'broadcast_requested_at' => now(),
            ]);

            return $this->sendResponse([], 'Broadcast request sent to admin.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            if ($longTermJob->status === 'running') {
                return $this->sendError('Cannot delete a running job.', [], 422);
            }

            $longTermJob->delete();

            return $this->sendResponse([], 'Job deleted successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (! $client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $editableStatuses = ['pending_approval', 'marketplace', 'rejected'];
            if (! in_array($longTermJob->status, $editableStatuses)) {
                return $this->sendError('This job cannot be edited in its current status.', [], 422);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'cover_image' => 'nullable|image|max:5120',

                'job_address' => 'sometimes|required|string|max:500',
                'home_city' => 'sometimes|required|string|max:100',
                'home_province' => 'sometimes|required|string|max:100',
                'home_postal_code' => 'sometimes|required|string|max:20',
                'country' => 'sometimes|required|string|max:100',
                'location_id' => 'nullable|integer|exists:locations,id',

                'start_date' => 'sometimes|required|date',
                'end_date' => 'nullable|date|after:start_date',

                'compensation_amount' => 'sometimes|required|numeric|min:0',
                'compensation_currency' => 'nullable|string|size:3',
                'compensation_type' => 'nullable|in:per_hour,per_week,per_month,flat',

                'children' => 'sometimes|required|array|min:1',
                'children.*.first_name' => 'required_with:children|string|max:255',
                'children.*.last_name' => 'nullable|string|max:255',
                'children.*.date_of_birth' => 'nullable|date',
                'children.*.gender' => 'nullable|in:male,female,other',
                'children.*.interests' => 'nullable|string',
                'children.*.allergies' => 'nullable|string',

                'schedules' => 'sometimes|required|array|min:1',
                'schedules.*.day_of_week' => 'required_with:schedules|integer|between:0,6',
                'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
                'schedules.*.end_time' => 'required_with:schedules|date_format:H:i|after:schedules.*.start_time',

                'bilingual_preference' => 'nullable|boolean',
                'special_needs' => 'nullable|boolean',
                'school_activity' => 'nullable|boolean',
                'has_housekeeper' => 'nullable|boolean',
                'live_in_required' => 'nullable|boolean',
                'paid_vacation' => 'nullable|in:vacation,holidays,vacation_and_holidays,none',
                'accommodation_quality' => 'nullable|in:excellent,not_bad,somewhat_comfortable,none',
                'tips_policy' => 'nullable|in:yes,no,sometimes,none',
                'room_with_wifi' => 'nullable|boolean',
                'negotiable_salary' => 'nullable|in:yes,no,open',

                'family_schedule' => 'nullable|string',
                'household_tasks' => 'nullable|string',
                'family_philosophy' => 'nullable|string',
                'play_dates' => 'nullable|string',
                'addons' => 'nullable|string',
                'home_description' => 'nullable|string',
                'neighborhood_description' => 'nullable|string',
                'nanny_experience' => 'nullable|string',
                'payment_norms' => 'nullable|string',
                'consent_description' => 'nullable|string',
                'child_attachment' => 'nullable|string',
            ]);

            if ($request->hasFile('cover_image')) {
                if ($longTermJob->cover_image) {
                    Storage::disk('public')->delete($longTermJob->cover_image);
                }
                $validated['cover_image'] = $request->file('cover_image')->store('jobs/covers', 'public');
            }

            // Resubmitting a rejected job resets it to pending_approval
            if ($longTermJob->status === 'rejected') {
                $validated['status'] = 'pending_approval';
            }

            $longTermJob->update(collect($validated)->except(['children', 'schedules'])->toArray());

            if (isset($validated['children'])) {
                $longTermJob->children()->delete();
                foreach ($validated['children'] as $child) {
                    $longTermJob->children()->create($child);
                }
            }

            if (isset($validated['schedules'])) {
                $longTermJob->schedules()->delete();
                foreach ($validated['schedules'] as $schedule) {
                    $longTermJob->schedules()->create($schedule);
                }
            }

            return $this->sendResponse(
                $longTermJob->fresh()->load(['schedules', 'children', 'location']),
                $longTermJob->status === 'pending_approval' ? 'Job resubmitted for approval.' : 'Job updated successfully.',
                200
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
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

            $validated = $request->validate([
                // Basic info
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'cover_image' => 'nullable|image|max:5120',

                // Address
                'job_address' => 'required|string|max:500',
                'home_city' => 'required|string|max:100',
                'home_province' => 'required|string|max:100',
                'home_postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'location_id' => 'nullable|integer|exists:locations,id',

                // Schedule window
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',

                // Compensation
                'compensation_amount' => 'required|numeric|min:0',
                'compensation_currency' => 'nullable|string|size:3',
                'compensation_type' => 'nullable|in:per_hour,per_week,per_month,flat',

                // Children
                'children' => 'required|array|min:1',
                'children.*.first_name' => 'required|string|max:255',
                'children.*.last_name' => 'nullable|string|max:255',
                'children.*.date_of_birth' => 'nullable|date',
                'children.*.gender' => 'nullable|in:male,female,other',
                'children.*.interests' => 'nullable|string',
                'children.*.allergies' => 'nullable|string',

                // Weekly schedule
                'schedules' => 'required|array|min:1',
                'schedules.*.day_of_week' => 'required|integer|between:0,6',
                'schedules.*.start_time' => 'required|date_format:H:i',
                'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',

                // Primary contact
                'primary_first_name' => 'nullable|string|max:255',
                'primary_last_name' => 'nullable|string|max:255',
                'primary_email' => 'nullable|email|max:255',
                'primary_phone' => 'nullable|string|max:50',

                // Secondary contact
                'secondary_first_name' => 'nullable|string|max:255',
                'secondary_last_name' => 'nullable|string|max:255',
                'secondary_email' => 'nullable|email|max:255',
                'secondary_phone' => 'nullable|string|max:50',

                // Requirements
                'bilingual_preference' => 'nullable|boolean',
                'special_needs' => 'nullable|boolean',
                'school_activity' => 'nullable|boolean',
                'has_housekeeper' => 'nullable|boolean',
                'prepare_meals' => 'nullable|boolean',
                'travel_with_family' => 'nullable|boolean',
                'live_in_required' => 'nullable|boolean',
                'paid_vacation' => 'nullable|in:vacation,holidays,vacation_and_holidays,none',
                'accommodation_quality' => 'nullable|in:excellent,not_bad,somewhat_comfortable,none',
                'tips_policy' => 'nullable|in:yes,no,sometimes,none',
                'room_with_wifi' => 'nullable|boolean',
                'negotiable_salary' => 'nullable|in:yes,no,open',
                'spouse_work_from_home' => 'nullable|in:yes_i_do,yes_spouse,yes_both,no',
                'nanny_own_car' => 'nullable|in:yes,no,other',

                // Additional information
                'family_schedule' => 'nullable|string',
                'household_tasks' => 'nullable|string',
                'family_philosophy' => 'nullable|string',
                'play_dates' => 'nullable|string',
                'addons' => 'nullable|string',
                'home_description' => 'nullable|string',
                'neighborhood_description' => 'nullable|string',
                'nanny_experience' => 'nullable|string',
                'payment_norms' => 'nullable|string',
                'consent_description' => 'nullable|string',
                'child_attachment' => 'nullable|string',
            ]);

            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('jobs/covers', 'public');
            }

            // Long-term jobs always go to pending_approval — no auto-post, no payment
            $job = LongTermJob::create([
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
                'primary_first_name' => $validated['primary_first_name'] ?? null,
                'primary_last_name' => $validated['primary_last_name'] ?? null,
                'primary_email' => $validated['primary_email'] ?? null,
                'primary_phone' => $validated['primary_phone'] ?? null,
                'secondary_first_name' => $validated['secondary_first_name'] ?? null,
                'secondary_last_name' => $validated['secondary_last_name'] ?? null,
                'secondary_email' => $validated['secondary_email'] ?? null,
                'secondary_phone' => $validated['secondary_phone'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'compensation_amount' => $validated['compensation_amount'],
                'compensation_currency' => $validated['compensation_currency'] ?? 'cad',
                'compensation_type' => $validated['compensation_type'] ?? 'per_hour',
                'bilingual_preference' => $validated['bilingual_preference'] ?? false,
                'special_needs' => $validated['special_needs'] ?? false,
                'school_activity' => $validated['school_activity'] ?? false,
                'has_housekeeper' => $validated['has_housekeeper'] ?? false,
                'prepare_meals' => $validated['prepare_meals'] ?? false,
                'travel_with_family' => $validated['travel_with_family'] ?? false,
                'live_in_required' => $validated['live_in_required'] ?? false,
                'paid_vacation' => $validated['paid_vacation'] ?? null,
                'accommodation_quality' => $validated['accommodation_quality'] ?? 'none',
                'tips_policy' => $validated['tips_policy'] ?? 'none',
                'room_with_wifi' => $validated['room_with_wifi'] ?? false,
                'negotiable_salary' => $validated['negotiable_salary'] ?? 'open',
                'spouse_work_from_home' => $validated['spouse_work_from_home'] ?? 'no',
                'nanny_own_car' => $validated['nanny_own_car'] ?? 'no',
                'family_schedule' => $validated['family_schedule'] ?? null,
                'household_tasks' => $validated['household_tasks'] ?? null,
                'family_philosophy' => $validated['family_philosophy'] ?? null,
                'play_dates' => $validated['play_dates'] ?? null,
                'addons' => $validated['addons'] ?? null,
                'home_description' => $validated['home_description'] ?? null,
                'neighborhood_description' => $validated['neighborhood_description'] ?? null,
                'nanny_experience' => $validated['nanny_experience'] ?? null,
                'payment_norms' => $validated['payment_norms'] ?? null,
                'consent_description' => $validated['consent_description'] ?? null,
                'child_attachment' => $validated['child_attachment'] ?? null,
                'status' => 'pending_approval',
            ]);

            foreach ($validated['children'] as $child) {
                $job->children()->create($child);
            }

            foreach ($validated['schedules'] as $schedule) {
                $job->schedules()->create($schedule);
            }

            return $this->sendResponse(
                $job->load(['schedules', 'children']),
                'Job submitted and is pending approval.',
                201
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
