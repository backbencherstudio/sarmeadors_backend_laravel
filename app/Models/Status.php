<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'name',
        'color',
        'serial',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
