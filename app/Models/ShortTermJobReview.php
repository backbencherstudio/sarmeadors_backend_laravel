<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortTermJobReview extends Model
{
    protected $fillable = [
        'short_term_job_id',
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
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
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
