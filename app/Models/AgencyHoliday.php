<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyHoliday extends Model
{
    protected $fillable = ['agency_id', 'holiday_name', 'date', 'location_ids', 'is_common'];

    protected $casts = [
        'location_ids' => 'array',
        'is_common' => 'boolean'
    ];

    protected $hidden = ['location_ids', 'created_at', 'updated_at'];
}
