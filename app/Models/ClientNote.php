<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNote extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'user_id',
        'title',
        'body',
        'is_pinned',
        'notify_admin_ids',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'notify_admin_ids' => 'array',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
