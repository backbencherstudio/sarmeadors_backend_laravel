<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An alternate email+password credential that signs in as the same portal
 * `User` (the candidate's primary account) — lets multiple people log in as
 * one candidate without creating extra `users` rows. See
 * AuthController::login for how this is authenticated.
 */
class CandidateSecondaryLogin extends Model
{
    protected $fillable = [
        'agency_id',
        'candidate_id',
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

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
