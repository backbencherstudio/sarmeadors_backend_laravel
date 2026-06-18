<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'short_term_job_id',
        'client_payment_method_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'cardholder_name',
        'billing_country',
        'billing_postal_code',
        'amount',
        'tax',
        'platform_fee',
        'note',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(ClientPaymentMethod::class, 'client_payment_method_id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function shortTermJob()
    {
        return $this->belongsTo(ShortTermJob::class);
    }
}
