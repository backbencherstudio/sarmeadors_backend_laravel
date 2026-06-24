<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgencyCandidateGlobalSetting extends Model
{
    use HasFactory;

    protected $table = 'agency_candidate_global_settings';

    protected $fillable = ['agency_id', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
}
