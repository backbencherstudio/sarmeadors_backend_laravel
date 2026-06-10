<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LongTermJobAttendance extends Model
{
    protected $table = 'long_term_job_attendance';

    protected $fillable = [
        'long_term_job_id',
        'candidate_id',
        'date',
        'check_in',
        'check_out',
        'is_absent',
        'notes',
    ];

    protected $casts = [
        'date'      => 'date',
        'is_absent' => 'boolean',
    ];

    public function getDurationMinutesAttribute(): int
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }

        $in  = \Carbon\Carbon::parse($this->check_in);
        $out = \Carbon\Carbon::parse($this->check_out);

        return max(0, $in->diffInMinutes($out));
    }

    public function job()
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
