<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilderSection extends Model
{
    protected $fillable = [
        'form_builder_block_id',
        'name',
        'serial',
    ];

    public function block()
    {
        return $this->belongsTo(FormBuilderBlock::class, 'form_builder_block_id');
    }

    public function fields()
    {
        return $this->hasMany(FormBuilderField::class, 'form_builder_section_id')->orderBy('serial');
    }
}
