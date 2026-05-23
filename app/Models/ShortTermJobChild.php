<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortTermJobChild extends Model
{
    protected $fillable = [
        'short_term_job_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'interests',
        'allergies',
    ];

    public function job()
    {
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
    }
}
