<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAvailability extends Model
{
    protected $fillable = ['candidate_id', 'timezone'];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function days()
    {
        return $this->hasMany(CandidateAvailabilityDay::class)->orderBy('day_of_week');
    }
}
