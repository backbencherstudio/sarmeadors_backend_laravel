<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'subdomain_prefix',
        'email',
        'mobile',
        'address',
        'logo',
        'logo_height',
        'favicon',
        'website',
        'font',
        'tax_id',
        'language',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'short_term_payment_required',
        'short_term_job_fee',
        'short_term_job_fee_currency',
        'short_term_auto_approve',
        'status',
        'max_users',
        'max_clients',
        'max_candidates',
        'total_users',
        'total_clients',
        'total_candidates',
    ];

    protected $casts = [
        'short_term_payment_required' => 'boolean',
        'short_term_auto_approve'     => 'boolean',
        'short_term_job_fee'          => 'decimal:2',
    ];

    public function hasStripeKeys(): bool
    {
        return !empty($this->stripe_publishable_key) && !empty($this->stripe_secret_key);
    }

    protected $hidden = [
        'stripe_secret_key',
        'stripe_publishable_key',
        'stripe_webhook_secret', 
    ];

    protected function stripeSecretKey(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : null,
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    protected function stripePublishableKey(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : null,
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    protected function stripeWebhookSecret(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? decrypt($value) : null,
            set: fn (?string $value) => $value ? encrypt($value) : null,
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function notes()
    {
        return $this->hasMany(AgencyNote::class);
    }

    public function messageTemplates()
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getLogoAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function getFaviconAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }
    
}
