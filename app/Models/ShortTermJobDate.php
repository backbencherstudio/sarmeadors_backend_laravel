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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_date' => 'date:Y-m-d',
        ];
    }

    public function job()
    {
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
    }
}
