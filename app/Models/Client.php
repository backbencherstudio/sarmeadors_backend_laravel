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
        'type_id',
        'location_id',
        'checklist_id',
        'tag_id',
        'status_id'
    ];

    protected $casts = [
        'type_id' => 'array',
        'location_id' => 'array',
        'checklist_id' => 'array',
        'tag_id' => 'array',
        'status_id' => 'array'
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
