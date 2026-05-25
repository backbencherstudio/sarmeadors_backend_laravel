<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilder extends Model
{
    protected $fillable = [
        'agency_id',
        'slug',
        'form_type',
        'application_type',
        'user_type',
        'job_type',
        'select_type',
        'logo',
        'title',
        'description',
        'button_label',
        'button_link',
        'status',
        'is_published',
    ];

    protected $casts = [
        'status'       => 'boolean',
        'is_published' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function blocks()
    {
        return $this->hasMany(FormBuilderBlock::class)->orderBy('serial');
    }

    public function submissions()
    {
        return $this->hasMany(FormBuilderSubmission::class);
    }

    public function getLogoAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }
}
