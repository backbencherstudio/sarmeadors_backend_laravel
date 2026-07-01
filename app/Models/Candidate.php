<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'hear_about_us',
        'image',
        'type_id',
        'location_id',
        'checklist_id',
        'tag_id',
        'status_id',
        'status_changed_at',

        // Personal info
        'date_of_birth',
        'nationality',
        'street_address',
        'city',
        'province',
        'postal_code',
        'country',

        // Professional info
        'hours_per_week',
        'bilingual',
        'pay_range_per_hour',
        'start_date',
        'last_position_end_reason',

        // Reference
        'reference_first_name',
        'reference_last_name',
        'reference_phone',
        'reference_email',
        'reference_relation',
        'reference_description',

        // Additional info
        'interested_in_iowa',
        'years_of_experience',
        'commitment',
        'available_for',
        'drivers_license',
        'cpr_first_aid',
        'vaccinations',
        'ok_with_pets',
        'ok_with_travel',
        'work_legally_in_us',
        'comfortable_paid_legally',
        'has_ssn',
    ];

    protected $casts = [
        'type_id' => 'array',
        'location_id' => 'array',
        'checklist_id' => 'array',
        'tag_id' => 'array',
        'status_id' => 'array',
        'available_for' => 'array',
        'interested_in_iowa' => 'boolean',
        'work_legally_in_us' => 'boolean',
        'comfortable_paid_legally' => 'boolean',
        'has_ssn' => 'boolean',
        'date_of_birth' => 'date',
        'start_date' => 'date',
        'status_changed_at' => 'datetime',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function reviews()
    {
        return $this->hasMany(LongTermJobReview::class);
    }

    public function clientLinks()
    {
        return $this->hasMany(ClientCandidate::class);
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

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'entity_id');
    }

    public function availability()
    {
        return $this->hasOne(CandidateAvailability::class);
    }

    public function unavailabilities()
    {
        return $this->hasMany(CandidateUnavailability::class);
    }
}
