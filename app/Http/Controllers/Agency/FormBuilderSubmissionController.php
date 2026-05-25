<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\FormBuilder;
use App\Models\FormBuilderAnswer;
use App\Models\FormBuilderField;
use App\Models\FormBuilderSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FormBuilderSubmissionController extends Controller
{
    // ─────────────────────────────────────────────
    // Public form submission (no auth required)
    // POST /public/form-builders/{slug}/submit
    //
    // Required body fields for registration forms:
    //   first_name, last_name, email, mobile,
    //   hear_about_us, type_id (array), location_id (array)
    //
    // Optional body:
    //   image (file)
    //
    // Custom field answers:
    //   answers: { "<field_id>": <value>, ... }
    //   Files for file fields: files.<field_id> (multipart)
    // ─────────────────────────────────────────────
    public function submit(Request $request, $slug)
    {
        $form = FormBuilder::where('slug', $slug)
            ->where('is_published', true)
            ->where('status', true)
            ->firstOrFail();

        // ── 1. Validate required registration fields ──────────────────
        $baseRules = [];

        if ($form->application_type === 'registration') {
            $baseRules = [
                'first_name'    => 'required|string|max:255',
                'last_name'     => 'nullable|string|max:255',
                'email'         => 'required|email|max:255',
                'mobile'        => 'nullable|string|max:30',
                'hear_about_us' => 'nullable|string',
                'type_id'       => 'nullable|array',
                'location_id'   => 'nullable|array',
                'image'         => 'nullable|image|max:10240',
            ];

            $emailTable = $form->user_type === 'client' ? 'clients' : 'candidates';
            $baseRules['email'] = "required|email|max:255|unique:{$emailTable},email";
        }

        // ── 2. Build dynamic rules from mandatory custom fields ────────
        $customFields = $this->getPublishedFields($form);
        $dynamicRules = [];

        foreach ($customFields as $field) {
            if ($field->is_mandatory) {
                $dynamicRules["answers.{$field->id}"] = 'required';
            }
        }

        $validator = Validator::make($request->all(), array_merge($baseRules, $dynamicRules));

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $entityType = null;
            $entityId   = null;

            // ── 3. Create Client or Candidate for registration forms ───
            if ($form->application_type === 'registration') {

                $imagePath = null;
                if ($request->hasFile('image')) {
                    $imagePath = $request->file('image')->store('registrations/images', 'public');
                }

                if ($form->user_type === 'client') {

                    $entity = Client::create([
                        'agency_id'     => $form->agency_id,
                        'first_name'    => $request->first_name,
                        'last_name'     => $request->last_name,
                        'email'         => $request->email,
                        'mobile'        => $request->mobile,
                        'hear_about_us' => $request->hear_about_us,
                        'image'         => $imagePath,
                        'type_id'       => $request->type_id,
                        'location_id'   => $request->location_id,
                    ]);

                    $entityType = Client::class;

                } elseif ($form->user_type === 'candidate') {

                    $entity = Candidate::create([
                        'agency_id'     => $form->agency_id,
                        'first_name'    => $request->first_name,
                        'last_name'     => $request->last_name,
                        'email'         => $request->email,
                        'mobile'        => $request->mobile,
                        'hear_about_us' => $request->hear_about_us,
                        'image'         => $imagePath,
                        'type_id'       => $request->type_id,
                        'location_id'   => $request->location_id,
                    ]);

                    $entityType = Candidate::class;
                }

                $entityId = $entity->id ?? null;
            }

            // ── 4. Create submission record ────────────────────────────
            $submission = FormBuilderSubmission::create([
                'form_builder_id' => $form->id,
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            // ── 5. Store custom field answers ──────────────────────────
            $allowedFieldIds = $customFields->pluck('id')->flip();
            $answers         = $request->input('answers', []);
            $insertData      = [];

            foreach ($answers as $fieldId => $value) {
                if (!isset($allowedFieldIds[$fieldId])) {
                    continue;
                }

                $field = $customFields->firstWhere('id', (int) $fieldId);

                // Handle file / media fields submitted as multipart
                if (in_array($field->field_type, ['file_upload', 'file_with_additional_info', 'list_files', 'signature_field'])) {
                    if ($request->hasFile("files.{$fieldId}")) {
                        $uploaded = [];
                        foreach ((array) $request->file("files.{$fieldId}") as $file) {
                            $uploaded[] = $file->store('form-builder/uploads', 'public');
                        }
                        $value = $uploaded;
                    }
                }

                if ($request->hasFile("files.{$fieldId}") && $field->field_type === 'video_recorder') {
                    $value = $request->file("files.{$fieldId}")->store('form-builder/videos', 'public');
                }

                $insertData[] = [
                    'form_builder_submission_id' => $submission->id,
                    'form_builder_field_id'      => (int) $fieldId,
                    'value'                      => json_encode($value),
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ];
            }

            if (!empty($insertData)) {
                FormBuilderAnswer::insert($insertData);
            }

            DB::commit();

            return response()->json([
                'status'        => true,
                'message'       => 'Submitted successfully',
                'submission_id' => $submission->id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    // List submissions for a form builder (agency only)
    // GET /agency/form-builders/{id}/submissions
    // ─────────────────────────────────────────────
    public function index($id)
    {
        $form = $this->ownerForm($id);

        $submissions = FormBuilderSubmission::with(['answers.field'])
            ->where('form_builder_id', $form->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['status' => true, 'data' => $submissions]);
    }

    // ─────────────────────────────────────────────
    // Get single submission detail
    // GET /agency/form-builder-submissions/{id}
    // ─────────────────────────────────────────────
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $submission = FormBuilderSubmission::with(['answers.field', 'entity'])
            ->whereHas('formBuilder', fn($q) => $q->where('agency_id', $agencyId))
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $submission]);
    }

    // ───────────── helpers ─────────────

    private function ownerForm($id)
    {
        return FormBuilder::where('id', $id)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();
    }

    private function getPublishedFields(FormBuilder $form)
    {
        return FormBuilderField::whereHas('section.block', function ($q) use ($form) {
            $q->where('form_builder_id', $form->id);
        })->get();
    }
}
