<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortTermJob extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'location_id',
        'title',
        'description',
        'cover_image',
        'job_address',
        'home_city',
        'home_province',
        'home_postal_code',
        'country',
        'compensation_amount',
        'compensation_currency',
        'compensation_type',
        'status',
        'stripe_payment_intent_id',
        'rejection_reason',
    ];

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function dates()
    {
        return $this->hasMany(ShortTermJobDate::class);
    }

    public function children()
    {
        return $this->hasMany(ShortTermJobChild::class);
    }
}
