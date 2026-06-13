<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'name',
        'user_type',
        'status',

        'sender_email',
        'receiver_email',
        'cc',
        'bcc',

        'location',
        'type',

        'subject',
        'body',

        'attachments',
        'agency_id',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
