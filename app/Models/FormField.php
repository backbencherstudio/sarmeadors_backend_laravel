<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $fillable = [
        'form_id',
        'label',
        'name',
        'type',
        'options',
        'placeholder',
        'is_required',
        'serial',
        'width',
        'validation_rules',
        'status'
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
