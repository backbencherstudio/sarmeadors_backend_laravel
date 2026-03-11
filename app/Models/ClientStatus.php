<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClientStatus extends Model
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

    public function statusTemplates()
    {
        return $this->hasMany(StatusTemplate::class, 'status_id');
    }
}
