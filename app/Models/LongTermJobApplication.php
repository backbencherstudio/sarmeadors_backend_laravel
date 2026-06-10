<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LongTermJobApplication extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'candidate_id',
        'agency_id',
        'application_message',
        'status',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interview(): HasOne
    {
        return $this->hasOne(LongTermJobInterview::class);
    }
}
