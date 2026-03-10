<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
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
        'stripe_account_id',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
