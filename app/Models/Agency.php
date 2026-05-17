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
        'phone',
        'address',
        'logo',
        'primary_color',
        'secondary_color',
        'favicon',
        'status',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret', 
    ];

    protected $hidden = [
        'stripe_secret_key',
        'stripe_publishable_key',
        'stripe_webhook_secret', 
    ];

    // ══════════════════════════════════
    // Encrypt all Stripe credentials
    // ══════════════════════════════════

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

    // ══════════════════════════════════
    // Helpers
    // ══════════════════════════════════

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasStripeKeys(): bool
    {
        return !empty($this->stripe_secret_key)
            && !empty($this->stripe_publishable_key);
    }

    public function hasWebhookSecret(): bool
    {
        return !empty($this->stripe_webhook_secret);
    }

    /**
     * Fully configured = keys + webhook secret
     */
    public function isStripeReady(): bool
    {
        return $this->hasStripeKeys() && $this->hasWebhookSecret();
    }
    
}
