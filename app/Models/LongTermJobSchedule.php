<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LongTermJobSchedule extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function job()
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }
}
