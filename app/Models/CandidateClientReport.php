<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateClientReport extends Model
{
    protected $fillable = [
        'agency_id',
        'candidate_id',
        'client_id',
        'short_term_job_id',
        'long_term_job_id',
        'job_type',
        'reason',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function shortTermJob(): BelongsTo
    {
        return $this->belongsTo(ShortTermJob::class);
    }

    public function longTermJob(): BelongsTo
    {
        return $this->belongsTo(LongTermJob::class);
    }
}
