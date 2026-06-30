<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An alternate email+password credential that signs in as the same portal
 * `User` (the client's primary account) — lets multiple people log in as
 * one client without creating extra `users` rows. See
 * AuthController::login for how this is authenticated.
 */
class ClientSecondaryLogin extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'user_id',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
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
