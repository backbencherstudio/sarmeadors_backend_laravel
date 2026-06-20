<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = [
        'agency_id',
        'name',
        'slug',
        'entity',
        'application_type',
        'user_type',
        'job_type',
        'schema',
        'status',
    ];

    protected $casts = [
        'schema' => 'array',
        'status' => 'boolean',
    ];

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('serial');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}
