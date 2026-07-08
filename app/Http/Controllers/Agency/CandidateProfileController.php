<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\FormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CandidateProfileController extends Controller
{
    public function __construct(private FormBuilderService $builder) {}

    // GET /agency/candidates/{id}/profile
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $submission = FormSubmission::where('entity_type', 'candidate')
            ->where('entity_id', $candidate->id)
            ->whereHas('form', fn ($q) => $q->where('agency_id', $agencyId)
                ->where('application_type', 'registration')
                ->where('user_type', 'candidate'))
            ->with('form')
            ->latest()
            ->first();

        $answers = (array) ($submission->data ?? []);
        $schema = $submission?->form?->schema ?? ['blocks' => []];

        $basicInformationFields = collect($this->builder->baseFields('candidate'))
            ->reject(fn ($field) => $field['name'] === 'password')
            ->map(fn ($field) => [
                'key' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'is_required' => $field['is_required'],
                'value' => $candidate->{$field['name']},
            ])
            ->values();

        $blocks = collect($schema['blocks'] ?? [])
            ->map(fn ($block) => [
                'name' => $block['name'] ?? null,
                'description' => $block['description'] ?? null,
                'sections' => collect($block['sections'] ?? [])
                    ->map(fn ($section) => [
                        'name' => $section['name'] ?? null,
                        'fields' => collect($section['fields'] ?? [])
                            ->map(fn ($field) => [
                                'key' => $field['name'] ?? null,
                                'label' => $field['label'] ?? null,
                                'type' => $field['type'] ?? null,
                                'placeholder' => $field['placeholder'] ?? null,
                                'is_required' => (bool) ($field['is_required'] ?? false),
                                'width' => $field['width'] ?? null,
                                'value' => $answers[$field['name'] ?? ''] ?? null,
                            ])
                            ->values(),
                    ])
                    ->values(),
            ])
            ->values();

        $blocks->prepend([
            'name' => 'Basic Information',
            'description' => null,
            'sections' => [[
                'name' => 'Basic Information',
                'fields' => $basicInformationFields,
            ]],
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $candidate->id,
                'form_id' => $submission?->form_id,
                'form_name' => $submission?->form?->name,
                'blocks' => $blocks,
            ],
        ]);
    }

    // PATCH /agency/candidates/{id}/profile
    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $data = $request->validate([
            // Personal information
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('candidates', 'email')->ignore($candidate->id)],
            'date_of_birth' => 'sometimes|nullable|date',
            'nationality' => 'sometimes|nullable|string|max:100',
            'phone_number' => 'sometimes|nullable|string|max:20',

            // Address
            'street_address' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'province' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',

            // Professional information
            'hours_per_week' => 'sometimes|nullable|string|max:100',
            'bilingual' => 'sometimes|nullable|string|max:100',
            'pay_range_per_hour' => 'sometimes|nullable|string|max:100',
            'start_date' => 'sometimes|nullable|date',
            'last_position_end_reason' => 'sometimes|nullable|string',

            // Reference
            'reference_first_name' => 'sometimes|nullable|string|max:255',
            'reference_last_name' => 'sometimes|nullable|string|max:255',
            'reference_phone' => 'sometimes|nullable|string|max:20',
            'reference_email' => 'sometimes|nullable|email|max:255',
            'reference_relation' => 'sometimes|nullable|string|max:100',
            'reference_description' => 'sometimes|nullable|string',

            // Additional information
            'interested_in_iowa' => 'sometimes|nullable|boolean',
            'years_of_experience' => ['sometimes', 'nullable', Rule::in(['2-5', '5-10', '10+'])],
            'commitment' => ['sometimes', 'nullable', Rule::in(['long_term', 'short_term', 'temporary'])],
            'available_for' => 'sometimes|nullable|array',
            'drivers_license' => ['sometimes', 'nullable', Rule::in(['dl_and_car', 'dl_only', 'neither'])],
            'cpr_first_aid' => ['sometimes', 'nullable', Rule::in(['yes', 'willing', 'no'])],
            'vaccinations' => ['sometimes', 'nullable', Rule::in(['yes', 'willing', 'no'])],
            'ok_with_pets' => ['sometimes', 'nullable', Rule::in(['dog', 'cat', 'neither'])],
            'ok_with_travel' => ['sometimes', 'nullable', Rule::in(['domestic', 'international', 'no_travel'])],
            'work_legally_in_us' => 'sometimes|nullable|boolean',
            'comfortable_paid_legally' => 'sometimes|nullable|boolean',
            'has_ssn' => 'sometimes|nullable|boolean',
        ]);

        // phone_number in the request maps to mobile on the model
        if (array_key_exists('phone_number', $data)) {
            $data['mobile'] = $data['phone_number'];
            unset($data['phone_number']);
        }

        // Capture old email before the update so we can find the linked user
        $oldEmail = $candidate->email;

        $candidate->update($data);

        $userUpdate = array_intersect_key($data, array_flip(['first_name', 'last_name', 'email', 'mobile']));
        if ($userUpdate) {
            User::where('email', $oldEmail)
                ->where('agency_id', $agencyId)
                ->update($userUpdate);
        }

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
        ]);
    }
}
