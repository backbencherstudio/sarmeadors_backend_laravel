<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'agency_id',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'user_type',
        'type_id',
        'location_id',
        'checklist_id',
        'tag_id'
    ];

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'entity_id');
    }

    public function dynamicFields()
    {
        return $this->hasManyThrough(
            FormFieldValue::class,
            FormSubmission::class,
            'entity_id',
            'submission_id',
            'id',
            'id'
        );
    }
}
