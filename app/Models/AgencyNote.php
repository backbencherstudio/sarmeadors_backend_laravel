<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyNote extends Model
{
    protected $fillable = [
        'agency_id',
        'user_id',
        'note',
        'pin_note',
        'is_read'
    ];

    protected $casts = [
        'pin_note' => 'boolean',
        'is_read' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
