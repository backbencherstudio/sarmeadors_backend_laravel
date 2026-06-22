<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LongTermJobChild extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'interests',
        'allergies',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
        ];
    }

    public function job()
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }
}
