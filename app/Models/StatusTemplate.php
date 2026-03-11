<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusTemplate extends Model
{
    protected $fillable = ['status_id', 'template_id', 'template_type', 'sort_order'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }
}
