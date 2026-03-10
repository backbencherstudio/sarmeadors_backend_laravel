<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormFieldController extends Controller
{
    // {
    // "form_id":1,
    // "label":"Gender",
    // "type":"select",
    // "options":["Male","Female"]
    // }

    public function store(Request $request)
    {
        $request->validate([
            'form_id' => 'required',
            'label' => 'required',
            'type' => 'required'
        ]);

        $field = FormField::create([
            'form_id' => $request->form_id,
            'label' => $request->label,
            'name' => Str::slug($request->label,'_'),
            'type' => $request->type,
            'options' => $request->options,
            'placeholder' => $request->placeholder,
            'is_required' => $request->is_required,
            'serial' => $request->serial ?? 0
        ]);

        return response()->json([
            'status' => true,
            'data' => $field
        ]);
    }

    // {
    // "fields":[
    // {"id":3,"serial":1},
    // {"id":1,"serial":2},
    // {"id":2,"serial":3}
    // ]
    // }

    public function reorder(Request $request)
    {
        foreach ($request->fields as $field) {

            FormField::where('id',$field['id'])
                ->update(['serial'=>$field['serial']]);
        }

        return response()->json([
            'status'=>true
        ]);
    }

}
