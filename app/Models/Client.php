<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = [];

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'entity_id');
    }
}
