<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateJobRequest extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'candidate_id',
        'short_term_job_id',
        'long_term_job_id',
        'long_term_job_application_id',
        'job_type',
        'status',
        'message',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function shortTermJob(): BelongsTo
    {
        return $this->belongsTo(ShortTermJob::class);
    }

    public function longTermJob(): BelongsTo
    {
        return $this->belongsTo(LongTermJob::class);
    }

    public function longTermJobApplication(): BelongsTo
    {
        return $this->belongsTo(LongTermJobApplication::class);
    }
}
