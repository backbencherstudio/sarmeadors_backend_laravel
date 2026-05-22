<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'location_id',
        'sub_location',
    ];
    
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }


}
