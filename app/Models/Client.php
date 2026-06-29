<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'agency_id',
        'first_name',
        'last_name',
        'email',
        'stripe_customer_id',
        'mobile',
        'image',
        'hear_about_us',
        'payment_status',

        'type_id',
        'location_id',
        'checklist_id',
        'tag_id',
        'status_id',
        'status_changed_at',
    ];

    protected $casts = [
        'type_id' => 'array',
        'location_id' => 'array',
        'checklist_id' => 'array',
        'tag_id' => 'array',
        'status_id' => 'array',
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

    public function paymentMethods()
    {
        return $this->hasMany(ClientPaymentMethod::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function candidateLinks()
    {
        return $this->hasMany(ClientCandidate::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'entity_id');
    }

    public function dynamicFields()
    {
        return $this->hasManyThrough(
            FormFieldValue::class,
            FormSubmission::class,
            'entity_id',
            'submission_id',
            'id',
            'id'
        );
    }
}
