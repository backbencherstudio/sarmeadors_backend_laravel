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
        'client_id',
        'agency_id',
        'title',
        'scheduled_date',
        'available_from',
        'available_to',
        'timezone',
        'location',
        'special_note',
        'reschedule_reason',
        'reschedule_requested_at',
        'reschedule_date',
        'reschedule_from',
        'reschedule_to',
        'cancellation_reason',
        'assigned_to',
        'interview_type',
        'interview_link',
        'description',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'reschedule_requested_at' => 'datetime',
        'reschedule_date' => 'date',
        'assigned_to' => 'array',
    ];

    /**
     * A reschedule the client has requested but the agency has not yet
     * approved by moving it onto the confirmed schedule.
     */
    public function hasPendingReschedule(): bool
    {
        return $this->status === 'scheduled' && $this->reschedule_requested_at !== null;
    }

    /**
     * The agency must act: a brand-new client request to set up, or a client
     * reschedule to approve. Scheduling is agency-only, so nothing sits with the
     * candidate.
     */
    public function isAwaitingAgency(): bool
    {
        return $this->status === 'requested' || $this->hasPendingReschedule();
    }

    /**
     * Agency-created events carry their own title; client/candidate requests
     * borrow the job's title.
     */
    public function displayTitle(): ?string
    {
        return $this->title ?: $this->job?->title;
    }

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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
