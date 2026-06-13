<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'name',
        'color',
        'serial',
        'any_reason',
        'reason',
        'type',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function statusTemplates()
    {
        return $this->hasMany(StatusTemplate::class, 'status_id');
    }
}
