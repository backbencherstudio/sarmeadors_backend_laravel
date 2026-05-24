<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAvailabilityDay extends Model
{
    protected $fillable = ['candidate_availability_id', 'day_of_week', 'is_available', 'start_time', 'end_time'];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function availability()
    {
        return $this->belongsTo(CandidateAvailability::class, 'candidate_availability_id');
    }
}
