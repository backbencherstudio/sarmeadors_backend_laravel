<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LongTermJobInterview extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'long_term_job_application_id',
        'candidate_id',
        'agency_id',
        'scheduled_date',
        'available_from',
        'available_to',
        'special_note',
        'interview_type',
        'interview_link',
        'description',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LongTermJobApplication::class, 'long_term_job_application_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
