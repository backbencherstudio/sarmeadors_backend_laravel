<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LongTermJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongTermJobController extends Controller
{
    private function resolveClient(Request $request): ?Client
    {
        $user   = $request->user();
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

            $query = LongTermJob::with(['schedules', 'children', 'location'])
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
                'jobs'   => $jobs,
            ], 'Jobs retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function show(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (!$client || $longTermJob->client_id !== $client->id) {
                return $this->sendError('Not found', [], 404);
            }

            $longTermJob->load(['schedules', 'children', 'location']);

            return $this->sendResponse($longTermJob, 'Job retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, LongTermJob $longTermJob): JsonResponse
    {
        try {
            $client = $this->resolveClient($request);

            if (!$client || $longTermJob->client_id !== $client->id) {
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

    public function store(Request $request): JsonResponse
    {
        try {
            $agency = $request->current_agency;
            $client = $this->resolveClient($request);

            if (!$client) {
                return $this->sendError('Client profile not found.', [], 404);
            }

            $validated = $request->validate([
                // Basic info
                'title'                    => 'required|string|max:255',
                'description'              => 'nullable|string',
                'cover_image'              => 'nullable|image|max:5120',

                // Address
                'job_address'              => 'required|string|max:500',
                'home_city'                => 'required|string|max:100',
                'home_province'            => 'required|string|max:100',
                'home_postal_code'         => 'required|string|max:20',
                'country'                  => 'required|string|max:100',
                'location_id'              => 'nullable|integer|exists:locations,id',

                // Schedule window
                'start_date'               => 'required|date|after_or_equal:today',
                'end_date'                 => 'nullable|date|after:start_date',

                // Compensation
                'compensation_amount'      => 'required|numeric|min:0',
                'compensation_currency'    => 'nullable|string|size:3',
                'compensation_type'        => 'nullable|in:per_hour,per_week,per_month,flat',

                // Children
                'children'                 => 'required|array|min:1',
                'children.*.first_name'    => 'required|string|max:255',
                'children.*.last_name'     => 'nullable|string|max:255',
                'children.*.date_of_birth' => 'nullable|date',
                'children.*.gender'        => 'nullable|in:male,female,other',
                'children.*.interests'     => 'nullable|string',
                'children.*.allergies'     => 'nullable|string',

                // Weekly schedule
                'schedules'                => 'required|array|min:1',
                'schedules.*.day_of_week'  => 'required|integer|between:0,6',
                'schedules.*.start_time'   => 'required|date_format:H:i',
                'schedules.*.end_time'     => 'required|date_format:H:i|after:schedules.*.start_time',

                // Requirements
                'bilingual_preference'     => 'nullable|boolean',
                'special_needs'            => 'nullable|boolean',
                'school_activity'          => 'nullable|boolean',
                'has_housekeeper'          => 'nullable|boolean',
                'live_in_required'         => 'nullable|boolean',
                'paid_vacation'            => 'nullable|boolean',
                'accommodation_quality'    => 'nullable|in:excellent,not_bad,somewhat_comfortable,none',
                'tips_policy'              => 'nullable|in:yes,no,sometimes,none',
                'room_with_wifi'           => 'nullable|boolean',
                'negotiable_salary'        => 'nullable|in:yes,no,open',

                // Additional information
                'family_schedule'          => 'nullable|string',
                'household_tasks'          => 'nullable|string',
                'family_philosophy'        => 'nullable|string',
                'play_dates'               => 'nullable|string',
                'addons'                   => 'nullable|string',
                'home_description'         => 'nullable|string',
                'neighborhood_description' => 'nullable|string',
                'nanny_experience'         => 'nullable|string',
                'payment_norms'            => 'nullable|string',
                'consent_description'      => 'nullable|string',
                'child_attachment'         => 'nullable|string',
            ]);

            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('jobs/covers', 'public');
            }

            // Long-term jobs always go to pending_approval — no auto-post, no payment
            $job = LongTermJob::create([
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
                'start_date'               => $validated['start_date'],
                'end_date'                 => $validated['end_date'] ?? null,
                'compensation_amount'      => $validated['compensation_amount'],
                'compensation_currency'    => $validated['compensation_currency'] ?? 'cad',
                'compensation_type'        => $validated['compensation_type'] ?? 'per_hour',
                'bilingual_preference'     => $validated['bilingual_preference'] ?? false,
                'special_needs'            => $validated['special_needs'] ?? false,
                'school_activity'          => $validated['school_activity'] ?? false,
                'has_housekeeper'          => $validated['has_housekeeper'] ?? false,
                'live_in_required'         => $validated['live_in_required'] ?? false,
                'paid_vacation'            => $validated['paid_vacation'] ?? false,
                'accommodation_quality'    => $validated['accommodation_quality'] ?? 'none',
                'tips_policy'              => $validated['tips_policy'] ?? 'none',
                'room_with_wifi'           => $validated['room_with_wifi'] ?? false,
                'negotiable_salary'        => $validated['negotiable_salary'] ?? 'open',
                'family_schedule'          => $validated['family_schedule'] ?? null,
                'household_tasks'          => $validated['household_tasks'] ?? null,
                'family_philosophy'        => $validated['family_philosophy'] ?? null,
                'play_dates'               => $validated['play_dates'] ?? null,
                'addons'                   => $validated['addons'] ?? null,
                'home_description'         => $validated['home_description'] ?? null,
                'neighborhood_description' => $validated['neighborhood_description'] ?? null,
                'nanny_experience'         => $validated['nanny_experience'] ?? null,
                'payment_norms'            => $validated['payment_norms'] ?? null,
                'consent_description'      => $validated['consent_description'] ?? null,
                'child_attachment'         => $validated['child_attachment'] ?? null,
                'status'                   => 'pending_approval',
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
