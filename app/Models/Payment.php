<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'amount',
        'platform_fee',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'metadata'     => 'array',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
