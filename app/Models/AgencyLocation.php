<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgencyLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'location',
        'status',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
