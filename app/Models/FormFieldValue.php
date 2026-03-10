<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormFieldValue extends Model
{
    protected $guarded = [];

    public function field()
    {
        return $this->belongsTo(FormField::class,'form_field_id');
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class);
    }
}
