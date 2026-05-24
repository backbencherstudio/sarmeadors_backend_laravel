<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobMessage extends Model
{
    protected $fillable = [
        'long_term_job_id',
        'short_term_job_id',
        'sender_id',
        'thread',
        'message',
        'file_path',
        'file_name',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function longTermJob()
    {
        return $this->belongsTo(LongTermJob::class, 'long_term_job_id');
    }

    public function shortTermJob()
    {
        return $this->belongsTo(ShortTermJob::class, 'short_term_job_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }
}
