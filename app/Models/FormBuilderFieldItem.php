<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilderFieldItem extends Model
{
    protected $fillable = [
        'form_builder_field_id',
        'item_type',
        'label',
        'value',
        'meta',
        'serial',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function field()
    {
        return $this->belongsTo(FormBuilderField::class, 'form_builder_field_id');
    }
}
