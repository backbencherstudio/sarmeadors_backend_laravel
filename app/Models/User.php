<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'image',
        'mobile',
        'password',
        'agency_id',
        'is_owner',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getImageAttribute()
    {
        return ($this->attributes['image'] ?? null) ? asset('storage/'.$this->attributes['image']) : null;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function notes()
    {
        return $this->hasMany(AgencyNote::class);
    }

    public function clientSecondaryLogins()
    {
        return $this->hasMany(ClientSecondaryLogin::class);
    }

    public function candidateSecondaryLogins()
    {
        return $this->hasMany(CandidateSecondaryLogin::class);
    }
}
