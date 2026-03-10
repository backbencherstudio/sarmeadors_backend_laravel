<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
