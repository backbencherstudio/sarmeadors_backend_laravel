<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LongTermJob extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'location_id',
        'candidate_id',
        'title',
        'description',
        'cover_image',
        'job_address',
        'home_city',
        'home_province',
        'home_postal_code',
        'country',
        'start_date',
        'end_date',
        'compensation_amount',
        'compensation_currency',
        'compensation_type',
        // Requirements
        'bilingual_preference',
        'special_needs',
        'school_activity',
        'has_housekeeper',
        'live_in_required',
        'paid_vacation',
        'accommodation_quality',
        'tips_policy',
        'room_with_wifi',
        'negotiable_salary',
        // Additional information
        'family_schedule',
        'household_tasks',
        'family_philosophy',
        'play_dates',
        'addons',
        'home_description',
        'neighborhood_description',
        'nanny_experience',
        'payment_norms',
        'consent_description',
        'child_attachment',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'bilingual_preference' => 'boolean',
        'special_needs'        => 'boolean',
        'school_activity'      => 'boolean',
        'has_housekeeper'      => 'boolean',
        'live_in_required'     => 'boolean',
        'paid_vacation'        => 'boolean',
        'room_with_wifi'       => 'boolean',
        'start_date'           => 'date',
        'end_date'             => 'date',
        'compensation_amount'  => 'decimal:2',
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

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function children()
    {
        return $this->hasMany(LongTermJobChild::class);
    }

    public function schedules()
    {
        return $this->hasMany(LongTermJobSchedule::class)->orderBy('day_of_week');
    }

    public function attendance()
    {
        return $this->hasMany(LongTermJobAttendance::class);
    }

    public function nannyPayments()
    {
        return $this->hasMany(LongTermJobNannyPayment::class);
    }
}
