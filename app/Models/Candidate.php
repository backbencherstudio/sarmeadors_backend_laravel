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
        'type_id',
        'location_id',
    ];

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
