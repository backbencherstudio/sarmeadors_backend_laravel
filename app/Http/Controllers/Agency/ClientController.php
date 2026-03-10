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
    // "form_id":1,

    // "first_name":"John",
    // "last_name":"Doe",
    // "email":"john@gmail.com",
    // "mobile":"017000000",

    // "type_id":[1,3],
    // "location_id":[2,5],
    // "checklist_id":[4,7],
    // "tag_id":[2,8],
    // "status_id":[1,4],

    //     "fields":{
    //         "1":"Male",
    //         "2":"1995-10-10",
    //         "3":"123456789"
    //     }
    // }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'form_id' => 'required|exists:forms,id',
            'first_name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'mobile' => 'required',
            'fields' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            $agencyId = auth('api')->user()->agency_id;

            $form = Form::where('id', $request->form_id)
                ->where('agency_id', $agencyId)
                ->first();

            if (!$form) {
                return response()->json([
                    'status' => false,
                    'message' => 'Form not found'
                ], 404);
            }

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

            if ($request->has('fields')) {

                foreach ($request->fields as $fieldId => $value) {

                    $field = FormField::where('id', $fieldId)
                        ->where('form_id', $form->id)
                        ->first();

                    if (!$field) {
                        continue;
                    }

                    FormFieldValue::create([
                        'submission_id' => $submission->id,
                        'form_field_id' => $fieldId,
                        'value' => $value
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Client created successfully',
                'data' => $client
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
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
