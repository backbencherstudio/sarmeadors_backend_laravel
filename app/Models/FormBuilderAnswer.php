<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilderAnswer extends Model
{
    protected $fillable = [
        'form_builder_submission_id',
        'form_builder_field_id',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function submission()
    {
        return $this->belongsTo(FormBuilderSubmission::class, 'form_builder_submission_id');
    }

    public function field()
    {
        return $this->belongsTo(FormBuilderField::class, 'form_builder_field_id');
    }
}
