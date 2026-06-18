<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCandidate extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'candidate_id',
        'status',
        'notes',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
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

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
