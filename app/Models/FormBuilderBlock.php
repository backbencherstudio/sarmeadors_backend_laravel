<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilderBlock extends Model
{
    protected $fillable = [
        'form_builder_id',
        'title',
        'description',
        'serial',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function formBuilder()
    {
        return $this->belongsTo(FormBuilder::class);
    }

    public function sections()
    {
        return $this->hasMany(FormBuilderSection::class, 'form_builder_block_id')->orderBy('serial');
    }
}
