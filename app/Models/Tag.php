<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
