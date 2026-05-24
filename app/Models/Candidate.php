<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'hear_about_us',
        'image',

        'type_id',
        'location_id',
    ];

    protected $casts = [
        'type_id' => 'array',
        'location_id' => 'array',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function reviews()
    {
        return $this->hasMany(LongTermJobReview::class);
    }

    public function getAverageRatingAttribute(): ?float
    {
        if ($this->relationLoaded('reviews') && $this->reviews->isNotEmpty()) {
            return round($this->reviews->avg('rating'), 1);
        }

        return round((float) $this->reviews()->avg('rating'), 1) ?: null;
    }

    public function getReviewsCountAttribute(): int
    {
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->count();
        }

        return $this->reviews()->count();
    }

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
