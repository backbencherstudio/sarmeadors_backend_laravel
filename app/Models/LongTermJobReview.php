<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LongTermJobReview extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'candidate_id',
        'client_id',
        'agency_id',
        'rating',
        'review',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
