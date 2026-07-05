<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class Client extends Model
{
    public const DEFAULT_PASSWORD = '12345678';

    protected $fillable = [
        'agency_id',
        'user_id',
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

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if ($client->user_id) {
                return;
            }

            $user = User::where('email', $client->email)
                ->where('agency_id', $client->agency_id)
                ->first();

            if (! $user) {
                $user = User::create([
                    'agency_id' => $client->agency_id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'mobile' => $client->mobile,
                    'password' => self::DEFAULT_PASSWORD,
                ]);
                $user->assignRole(Role::findOrCreate('client', 'api'));
            }

            $client->user_id = $user->id;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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
