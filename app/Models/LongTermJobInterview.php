<?php

namespace App\Models;

use Carbon\Carbon;
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
        'reschedule_reason',
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

    /**
     * The exact date-time the interview starts (scheduled date + available_from).
     */
    public function startsAt(): ?Carbon
    {
        if (! $this->scheduled_date || ! $this->available_from) {
            return null;
        }

        return Carbon::parse($this->scheduled_date->toDateString().' '.$this->available_from);
    }
}
