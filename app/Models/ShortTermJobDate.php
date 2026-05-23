<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortTermJobDate extends Model
{
    protected $fillable = [
        'short_term_job_id',
        'booking_date',
        'start_time',
        'end_time',
    ];

    public function job()
    {
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
    }
}
