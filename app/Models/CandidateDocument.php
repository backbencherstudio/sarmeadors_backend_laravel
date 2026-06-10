<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateDocument extends Model
{
    protected $fillable = [
        'agency_id',
        'candidate_id',
        'document_template_id',
        'required_key',
        'category',
        'title',
        'description',
        'file_path',
        'original_file_name',
        'mime_type',
        'size',
        'status',
        'signature',
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

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}
