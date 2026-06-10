<?php

namespace App\Http\Resources\Candidate;

use App\Models\CandidateAvailabilityDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateAvailabilityDay
 */
class AvailabilityDayResource extends JsonResource
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'day_name' => self::DAY_NAMES[$this->day_of_week] ?? null,
            'is_available' => $this->is_available,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ];
    }
}
