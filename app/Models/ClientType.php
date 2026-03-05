<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientType extends Model
{
    protected $fillable = [
        'agency_id',
        'name',
        'status',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
