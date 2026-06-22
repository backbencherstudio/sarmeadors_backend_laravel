<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'currency',
        'countries',
        'default_from_email',
        'default_reply_email',
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
        'short_term_auto_approve' => 'boolean',
        'short_term_job_fee' => 'decimal:2',
        'countries' => 'array',
        'location_ids' => 'array',
        'is_open' => 'boolean',
    ];

    public function hasStripeKeys(): bool
    {
        return ! empty($this->stripe_publishable_key) && ! empty($this->stripe_secret_key);
    }

    /**
     * The agency *intends* to charge a fee for short-term job postings,
     * regardless of whether Stripe is wired up yet.
     */
    public function shortTermPaymentIntended(): bool
    {
        return (bool) $this->short_term_payment_required && $this->short_term_job_fee > 0;
    }

    /**
     * Payment can actually be collected: the agency wants a fee AND Stripe is
     * configured. When this is false but {@see shortTermPaymentIntended()} is
     * true, the agency has a broken payment setup.
     */
    public function shortTermPaymentActive(): bool
    {
        return $this->shortTermPaymentIntended() && $this->hasStripeKeys();
    }

    protected $hidden = [
        'stripe_secret_key',
        'stripe_publishable_key',
        'stripe_webhook_secret',
    ];

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
        return $value ? asset('storage/'.$value) : null;
    }

    public function getFaviconAttribute($value)
    {
        return $value ? asset('storage/'.$value) : null;
    }

    public function subLocations()
    {
        return $this->hasMany(SubLocation::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
