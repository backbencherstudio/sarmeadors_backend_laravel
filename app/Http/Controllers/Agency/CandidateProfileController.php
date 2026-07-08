<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\FormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CandidateProfileController extends Controller
{
    /**
     * Base field keys handled as direct `candidates` table columns rather
     * than dynamic schema answers - kept in sync with
     * FormBuilderService::baseFields('candidate'), minus `password`.
     *
     * @var array<int, string>
     */
    private const BASIC_INFORMATION_KEYS = ['first_name', 'last_name', 'email', 'image', 'type_id', 'location_id'];

    public function __construct(private FormBuilderService $builder) {}

    // GET /agency/candidates/{id}/profile
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $submission = $this->registrationSubmission($candidate, $agencyId);

        $answers = (array) ($submission?->data ?? []);
        $schema = $submission?->form?->schema ?? ['blocks' => []];

        $basicInformationFields = collect($this->builder->baseFields('candidate'))
            ->reject(fn ($field) => $field['name'] === 'password')
            ->map(fn ($field) => [
                'key' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'is_required' => $field['is_required'],
                'value' => $field['name'] === 'image' ? $candidate->image_url : $candidate->{$field['name']},
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

        $submission = $this->registrationSubmission($candidate, $agencyId);
        $schema = $submission?->form?->schema ?? ['blocks' => []];

        $basicRules = [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('candidates', 'email')->ignore($candidate->id)],
            'image' => 'sometimes|nullable|file|mimes:jpeg,jpg,png,gif|max:10240',
            'type_id' => 'sometimes|nullable|array',
            'type_id.*' => [
                'integer',
                Rule::exists('types', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'candidate')),
            ],
            'location_id' => 'sometimes|nullable|array',
            'location_id.*' => [
                'integer',
                Rule::exists('locations', 'id')->where('agency_id', $agencyId),
            ],
        ];

        $dynamicRules = $this->dynamicValidationRules($schema);

        // Hard-coded basic-information rules win if a schema field happens
        // to reuse one of those reserved names.
        $data = $request->validate(array_merge($dynamicRules, $basicRules));

        $basicData = array_intersect_key($data, array_flip(self::BASIC_INFORMATION_KEYS));
        $dynamicData = array_diff_key($data, array_flip(self::BASIC_INFORMATION_KEYS));

        if ($request->hasFile('image')) {
            if ($candidate->image) {
                Storage::disk('public')->delete($candidate->image);
            }

            $basicData['image'] = $request->file('image')->store('candidates', 'public');
        } else {
            unset($basicData['image']);
        }

        // Capture old email before the update so we can find the linked user
        $oldEmail = $candidate->email;

        if ($basicData) {
            $candidate->update($basicData);
        }

        if ($submission && $dynamicData) {
            $submission->data = array_merge((array) $submission->data, $dynamicData);
            $submission->save();

            $columnUpdates = $this->builder->mapAnswersToColumns(Candidate::class, 'candidate', $schema, $dynamicData);

            if ($columnUpdates) {
                $candidate->update($columnUpdates);
            }
        }

        $userUpdate = array_intersect_key($basicData, array_flip(['first_name', 'last_name', 'email']));
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

    /**
     * The candidate's most recent registration-form submission, whose
     * schema and answers drive both the profile display and its updates.
     */
    private function registrationSubmission(Candidate $candidate, int $agencyId): ?FormSubmission
    {
        return FormSubmission::where('entity_type', 'candidate')
            ->where('entity_id', $candidate->id)
            ->whereHas('form', fn ($q) => $q->where('agency_id', $agencyId)
                ->where('application_type', 'registration')
                ->where('user_type', 'candidate'))
            ->with('form')
            ->latest()
            ->first();
    }

    /**
     * Validation rules for a partial update of the schema's dynamic answers:
     * "sometimes" so unmentioned fields are left alone, but a required
     * field can't be blanked out once it is mentioned.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    private function dynamicValidationRules(array $schema): array
    {
        $rules = [];

        foreach ($this->builder->flattenFields($schema) as $field) {
            $name = $field['name'] ?? null;

            if (! $name) {
                continue;
            }

            $fieldRules = [($field['is_required'] ?? false) ? 'required' : 'nullable'];

            switch ($field['type'] ?? null) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'date_picker':
                case 'month_picker':
                case 'year_picker':
                    $fieldRules[] = 'date';
                    break;
                case 'salary_range':
                case 'multi_select_checkbox':
                case 'checkbox_table':
                case 'radio_table':
                    $fieldRules[] = 'array';
                    break;
            }

            $custom = $field['validation_rules'] ?? null;

            if (is_string($custom) && $custom !== '') {
                $fieldRules = array_merge($fieldRules, explode('|', $custom));
            } elseif (is_array($custom)) {
                $fieldRules = array_merge($fieldRules, $custom);
            }

            $rules[$name] = 'sometimes|'.implode('|', array_values(array_unique($fieldRules)));
        }

        return $rules;
    }
}
