<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPaymentMethod extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'stripe_payment_method_id',
        'cardholder_name',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'exp_month' => 'integer',
            'exp_year' => 'integer',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
