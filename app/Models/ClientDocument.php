<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDocument extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'document_template_id',
        'title',
        'description',
        'status',
        'signature',
        'signed_content',
        'signed_content_type',
        'file_path',
        'signed_ip',
        'signed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'signed_at' => 'datetime',
    ];

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}
