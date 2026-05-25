<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyBusinessHour extends Model
{
    protected $fillable = ['agency_id', 'day', 'start_time', 'end_time', 'is_open'];

    protected $casts = [
        'is_open' => 'boolean'
    ];

    protected $hidden = ['created_at', 'updated_at'];
}
