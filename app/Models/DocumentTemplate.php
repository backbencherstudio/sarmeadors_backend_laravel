<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'notification_emails' => 'array',
        'admin_view_ids' => 'array',
    ];

    public function fields()
    {
        return $this->hasMany(DocumentTemplateField::class);
    }

    public function signers()
    {
        return $this->hasMany(DocumentTemplateSigner::class);
    }

    public function category()
    {
        return $this->belongsTo(Type::class, 'category_id');
    }

    public function triggerStatus()
    {
        return $this->belongsTo(Status::class, 'trigger_status_id');
    }
}
