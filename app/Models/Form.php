<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $guarded = [];

    public function fields()
    {
        return $this->hasMany(FormField::class)
            ->orderBy('serial');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}
