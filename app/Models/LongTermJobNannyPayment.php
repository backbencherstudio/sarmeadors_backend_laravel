<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LongTermJobNannyPayment extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'candidate_id',
        'agency_id',
        'amount',
        'currency',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function job()
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
