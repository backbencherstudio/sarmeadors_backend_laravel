<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'name',
        'type',
        'status',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
