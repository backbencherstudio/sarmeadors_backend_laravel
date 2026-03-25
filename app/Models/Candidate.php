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
        'date_of_birth',
        'nationality',
        'address',
        'city',
        'postal_code',
        'state',
        'country',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
