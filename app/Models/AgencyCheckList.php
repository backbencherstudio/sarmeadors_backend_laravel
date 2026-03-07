<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgencyCheckList extends Model
{
    use HasFactory;

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
