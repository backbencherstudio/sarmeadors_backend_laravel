<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplateField extends Model
{
    protected $guarded = []; 
    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}
