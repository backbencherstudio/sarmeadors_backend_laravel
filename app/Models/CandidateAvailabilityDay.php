<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAvailabilityDay extends Model
{
    /**
     * Map of lowercase day names to their stored day_of_week index.
     *
     * @var array<string, int>
     */
    public const DAYS = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];

    protected $fillable = ['candidate_availability_id', 'day_of_week', 'is_available', 'start_time', 'end_time'];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function availability()
    {
        return $this->belongsTo(CandidateAvailability::class, 'candidate_availability_id');
    }

    public static function dayIndex(string $day): ?int
    {
        return self::DAYS[strtolower($day)] ?? null;
    }

    public static function dayName(int $index): ?string
    {
        return array_flip(self::DAYS)[$index] ?? null;
    }
}
