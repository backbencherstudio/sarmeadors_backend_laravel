<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LongTermJobRefundRequest extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'client_id',
        'agency_id',
        'reason',
        'status',
        'agency_note',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
