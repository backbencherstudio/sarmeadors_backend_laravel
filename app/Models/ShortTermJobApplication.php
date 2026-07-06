<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortTermJobApplication extends Model
{
    protected $fillable = [
        'short_term_job_id',
        'candidate_id',
        'agency_id',
        'application_message',
        'status',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
