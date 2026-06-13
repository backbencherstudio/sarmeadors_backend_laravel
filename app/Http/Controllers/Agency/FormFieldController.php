<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormFieldController extends Controller
{
    // {
    // "form_id": 1,
    // "label": "Gender",
    // "type": "select",
    // "options": [
    //     "Male",
    //     "Female",
    //     "Other"
    // ],
    // "placeholder": "Select Gender",
    // "is_required": true,
    // "serial": 1,
    // "width": 6,
    // "validation_rules": "required",
    // "status": true
    // }

    // {
    // "form_id": 1,
    // "label": "Date of Birth",
    // "type": "date",
    // "placeholder": "Select DOB",
    // "is_required": true,
    // "serial": 2,
    // "width": 6,
    // "validation_rules": "required|date"
    // }

    public function store(Request $request)
    {
        $request->validate([
            'form_id' => 'required|exists:forms,id',
            'label' => 'required|string|max:255',
            'type' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $form = Form::where('id', $request->form_id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();

        $serial = FormField::where('form_id', $form->id)->max('serial') + 1;

        $field = FormField::create([
            'form_id' => $form->id,
            'label' => $request->label,
            'name' => Str::slug($request->label, '_'),
            'type' => $request->type,
            'options' => $request->options,
            'placeholder' => $request->placeholder,
            'is_required' => $request->boolean('is_required'),
            'serial' => $request->serial ?? $serial,
            'validation_rules' => $request->validation_rules,
            'width' => $request->width ?? 12,
            'status' => true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Field created',
            'data' => $field,
        ]);
    }

    // {
    // "fields": [
    //     {
    //     "id": 1,
    //     "serial": 1
    //     },
    //     {
    //     "id": 3,
    //     "serial": 2
    //     },
    //     {
    //     "id": 2,
    //     "serial": 3
    //     }
    // ]
    // }

    public function reorder(Request $request)
    {
        $request->validate([
            'fields' => 'required|array',
        ]);

        DB::beginTransaction();

        try {

            foreach ($request->fields as $field) {

                FormField::where('id', $field['id'])
                    ->update([
                        'serial' => $field['serial'],
                    ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Fields reordered',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
