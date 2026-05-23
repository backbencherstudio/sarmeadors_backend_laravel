<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\FormSubmission;
use App\Models\FormFieldValue;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ClientController extends Controller
{
    // {
    // "form_id": 1,

    // "first_name": "John",
    // "last_name": "Doe",
    // "email": "john@example.com",
    // "mobile": "01700000000",

    // "type_id": [1, 2],
    // "location_id": [3],
    // "checklist_id": [5, 6],
    // "tag_id": [2],
    // "status_id": [1],

    // "fields": {
    //     "1": "Male",
    //     "2": "1996-10-10",
    // }
    // }

    public function store(Request $request)
    {
        $agencyId = auth('api')->user()->agency_id;

        $form = Form::where('id',$request->form_id)
            ->where('agency_id',$agencyId)
            ->firstOrFail();

        $formFields = FormField::where('form_id',$form->id)
            ->where('status',1)
            ->get();

        $rules = [
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'mobile' => 'nullable|string|max:20',
        ];

        foreach ($formFields as $field) {

            if ($field->validation_rules) {

                $rules["fields.$field->id"] = $field->validation_rules;
            }

            if ($field->is_required) {

                $rules["fields.$field->id"] = 'required';
            }
        }


        $request->validate($rules);

        DB::beginTransaction();

        try {

            $client = Client::create([
                'agency_id' => $agencyId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'type_id' => $request->type_id,
                'location_id' => $request->location_id,
                'checklist_id' => $request->checklist_id,
                'tag_id' => $request->tag_id,
                'status_id' => $request->status_id
            ]);

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'entity_id' => $client->id
            ]);

            $allowedFields = $formFields->pluck('id')->toArray();

            $insertData = [];

            foreach ($request->fields ?? [] as $fieldId => $value) {

                if (!in_array($fieldId,$allowedFields)) {
                    continue;
                }

                $insertData[] = [
                    'submission_id' => $submission->id,
                    'form_field_id' => $fieldId,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (!empty($insertData)) {

                FormFieldValue::insert($insertData);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Client created successfully',
                'data' => $client
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ],500);
        }
    }

    public function show($id)
    {
        $client = Client::with([
            'submissions.values.field'
        ])->findOrFail($id);

        return response()->json($client);
    }

}
