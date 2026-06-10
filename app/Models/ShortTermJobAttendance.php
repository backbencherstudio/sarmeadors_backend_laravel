<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortTermJobAttendance extends Model
{
    protected $table = 'short_term_job_attendance';

    protected $fillable = [
        'short_term_job_id',
        'candidate_id',
        'booking_date',
        'check_in',
        'check_out',
        'notes',
    ];

    protected $casts = [
        'booking_date' => 'date',
    ];

    public function getDurationMinutesAttribute(): int
    {
        if (! $this->check_in || ! $this->check_out) {
            return 0;
        }

        $in = Carbon::parse($this->check_in);
        $out = Carbon::parse($this->check_out);

        return max(0, $in->diffInMinutes($out));
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
