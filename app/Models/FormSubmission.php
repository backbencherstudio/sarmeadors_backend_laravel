<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'entity_id',
        'entity_type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function values()
    {
        return $this->hasMany(FormFieldValue::class, 'submission_id');
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
