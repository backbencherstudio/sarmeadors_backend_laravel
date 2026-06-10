<?php

namespace App\Http\Resources\Candidate;

use App\Models\CandidateAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateAvailability
 */
class AvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'timezone' => $this->timezone,
            'days' => AvailabilityDayResource::collection(
                $this->days->sortBy('day_of_week')->values()
            ),
        ];
    }
}
