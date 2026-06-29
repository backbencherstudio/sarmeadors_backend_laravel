<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreUnavailabilityRequest;
use App\Http\Requests\Candidate\UpdateAvailabilityRequest;
use App\Http\Resources\Candidate\AvailabilityResource;
use App\Http\Resources\Candidate\UnavailabilityResource;
use App\Models\Candidate;
use App\Models\CandidateAvailability;
use App\Models\CandidateAvailabilityDay;
use App\Models\CandidateUnavailability;
use App\Traits\ResolvesCandidate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateAvailabilityController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/availability
    public function show(Request $request): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);

        return $this->sendResponse([
            'availability' => new AvailabilityResource($this->resolveOrCreateAvailability($candidate)),
            'unavailabilities' => UnavailabilityResource::collection($this->unavailabilitiesFor($candidate)),
        ], 'Availability retrieved successfully.', 200);
    }

    // PUT /candidate/availability
    public function update(UpdateAvailabilityRequest $request): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);
        $validated = $request->validated();

        $availability = $this->resolveOrCreateAvailability($candidate);

        if (isset($validated['timezone'])) {
            $availability->update(['timezone' => $validated['timezone']]);
        }

        if (isset($validated['days'])) {
            foreach ($validated['days'] as $dayData) {
                CandidateAvailabilityDay::updateOrCreate(
                    [
                        'candidate_availability_id' => $availability->id,
                        'day_of_week' => CandidateAvailabilityDay::dayIndex($dayData['day']),
                    ],
                    [
                        'is_available' => $dayData['is_available'],
                        'start_time' => $dayData['from'] ?? null,
                        'end_time' => $dayData['to'] ?? null,
                    ]
                );
            }
        }

        $availability->load('days');

        return $this->sendResponse([
            'availability' => new AvailabilityResource($availability),
            'unavailabilities' => UnavailabilityResource::collection($this->unavailabilitiesFor($candidate)),
        ], 'Availability updated successfully.', 200);
    }

    // GET /candidate/availability/unavailabilities
    public function indexUnavailabilities(Request $request): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);

        return $this->sendResponse(
            UnavailabilityResource::collection($this->unavailabilitiesFor($candidate))->resolve(),
            'Unavailabilities retrieved successfully.',
            200
        );
    }

    // POST /candidate/availability/unavailabilities
    public function storeUnavailability(StoreUnavailabilityRequest $request): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);
        $validated = $request->validated();

        $unavailability = CandidateUnavailability::create([
            'candidate_id' => $candidate->id,
            'title' => $validated['title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return $this->sendResponse(new UnavailabilityResource($unavailability), 'Unavailability created successfully.', 201);
    }

    // DELETE /candidate/availability/unavailabilities/{unavailability}
    public function destroyUnavailability(Request $request, CandidateUnavailability $unavailability): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        if (! $candidate || $unavailability->candidate_id !== $candidate->id) {
            return $this->sendError('Not found.', [], 404);
        }

        $unavailability->delete();

        return $this->sendResponse([], 'Unavailability deleted successfully.', 200);
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

    /**
     * @return Collection<int, CandidateUnavailability>
     */
    private function unavailabilitiesFor(Candidate $candidate): Collection
    {
        return CandidateUnavailability::where('candidate_id', $candidate->id)
            ->orderBy('start_date')
            ->get();
    }
}
