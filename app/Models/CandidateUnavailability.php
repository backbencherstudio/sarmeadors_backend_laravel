<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateUnavailability extends Model
{
    protected $fillable = ['candidate_id', 'title', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
