<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilderSubmission extends Model
{
    protected $fillable = [
        'form_builder_id',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
    ];

    public function formBuilder()
    {
        return $this->belongsTo(FormBuilder::class);
    }

    public function entity()
    {
        return $this->morphTo();
    }

    public function answers()
    {
        return $this->hasMany(FormBuilderAnswer::class, 'form_builder_submission_id');
    }
}
