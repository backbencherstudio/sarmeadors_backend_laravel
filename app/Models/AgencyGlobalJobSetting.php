<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgencyGlobalJobSetting extends Model
{
    use HasFactory;

    protected $table = 'agency_global_job_settings';

    protected $fillable = [
        'agency_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
}
