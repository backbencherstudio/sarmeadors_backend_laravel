<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAvailability;
use App\Models\CandidateAvailabilityDay;
use App\Models\CandidateUnavailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CandidateAvailabilityController extends Controller
{
    private const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    private function resolveOrCreateAvailability(Candidate $candidate): CandidateAvailability
    {
        $availability = CandidateAvailability::with('days')
            ->where('candidate_id', $candidate->id)
            ->first();

        if (! $availability) {
            $availability = CandidateAvailability::create([
                'candidate_id' => $candidate->id,
                'timezone' => 'UTC',
            ]);

            for ($day = 0; $day <= 6; $day++) {
                CandidateAvailabilityDay::create([
                    'candidate_availability_id' => $availability->id,
                    'day_of_week' => $day,
                    'is_available' => false,
                    'start_time' => '12:00:00',
                    'end_time' => '12:00:00',
                ]);
            }

            $availability->load('days');
        }

        return $availability;
    }

    // GET /candidate/availability
    public function show(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $availability = $this->resolveOrCreateAvailability($candidate);
            $unavailabilities = CandidateUnavailability::where('candidate_id', $candidate->id)
                ->orderBy('start_date')
                ->get();

            return $this->sendResponse([
                'availability' => $this->formatAvailability($availability),
                'unavailabilities' => $this->formatUnavailabilities($unavailabilities),
            ], 'Availability retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /candidate/availability
    public function update(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $validated = $request->validate([
                'timezone' => 'sometimes|required|string|max:100',
                'days' => 'sometimes|required|array',
                'days.*.day_of_week' => 'required|integer|between:0,6',
                'days.*.is_available' => 'required|boolean',
                'days.*.start_time' => 'nullable|date_format:H:i',
                'days.*.end_time' => 'nullable|date_format:H:i',
            ]);

            $availability = $this->resolveOrCreateAvailability($candidate);

            if (isset($validated['timezone'])) {
                $availability->update(['timezone' => $validated['timezone']]);
            }

            if (isset($validated['days'])) {
                foreach ($validated['days'] as $dayData) {
                    CandidateAvailabilityDay::updateOrCreate(
                        [
                            'candidate_availability_id' => $availability->id,
                            'day_of_week' => $dayData['day_of_week'],
                        ],
                        [
                            'is_available' => $dayData['is_available'],
                            'start_time' => $dayData['start_time'] ?? null,
                            'end_time' => $dayData['end_time'] ?? null,
                        ]
                    );
                }
            }

            $availability->load('days');
            $unavailabilities = CandidateUnavailability::where('candidate_id', $candidate->id)
                ->orderBy('start_date')
                ->get();

            return $this->sendResponse([
                'availability' => $this->formatAvailability($availability),
                'unavailabilities' => $this->formatUnavailabilities($unavailabilities),
            ], 'Availability updated successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // GET /candidate/availability/unavailabilities
    public function indexUnavailabilities(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $unavailabilities = CandidateUnavailability::where('candidate_id', $candidate->id)
                ->orderBy('start_date')
                ->get();

            return $this->sendResponse($this->formatUnavailabilities($unavailabilities), 'Unavailabilities retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // POST /candidate/availability/unavailabilities
    public function storeUnavailability(Request $request): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $unavailability = CandidateUnavailability::create([
                'candidate_id' => $candidate->id,
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            return $this->sendResponse($this->formatUnavailability($unavailability), 'Unavailability created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /candidate/availability/unavailabilities/{unavailability}
    public function destroyUnavailability(Request $request, CandidateUnavailability $unavailability): JsonResponse
    {
        try {
            $candidate = $this->resolveCandidate($request);

            if (! $candidate || $unavailability->candidate_id !== $candidate->id) {
                return $this->sendError('Not found.', [], 404);
            }

            $unavailability->delete();

            return $this->sendResponse([], 'Unavailability deleted successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function formatAvailability(CandidateAvailability $availability): array
    {
        return [
            'id' => $availability->id,
            'timezone' => $availability->timezone,
            'days' => $availability->days
                ->sortBy('day_of_week')
                ->values()
                ->map(fn (CandidateAvailabilityDay $day): array => $this->formatAvailabilityDay($day))
                ->all(),
        ];
    }

    private function formatAvailabilityDay(CandidateAvailabilityDay $day): array
    {
        return [
            'id' => $day->id,
            'day_of_week' => $day->day_of_week,
            'day_name' => self::DAY_NAMES[$day->day_of_week] ?? null,
            'is_available' => $day->is_available,
            'start_time' => $day->start_time,
            'end_time' => $day->end_time,
        ];
    }

    private function formatUnavailabilities(Collection $unavailabilities): array
    {
        return $unavailabilities
            ->map(fn (CandidateUnavailability $unavailability): array => $this->formatUnavailability($unavailability))
            ->all();
    }

    private function formatUnavailability(CandidateUnavailability $unavailability): array
    {
        return [
            'id' => $unavailability->id,
            'title' => $unavailability->title,
            'start_date' => $unavailability->start_date?->toDateString(),
            'end_date' => $unavailability->end_date?->toDateString(),
        ];
    }
}
