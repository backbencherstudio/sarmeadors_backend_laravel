<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
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

    public function subLocations()
    {
        return $this->hasMany(SubLocation::class);
    }
}
