<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'hear_about_us',
        'image',
        
        'type_id',
        'location_id',
    ];

    protected $casts = [
        'type_id' => 'array',
        'location_id' => 'array',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
