<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
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
    public function show(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $submission = $this->builder->registrationSubmissionFor('candidate', $candidate->id, $agencyId);

        $blocks = $this->builder->profileBlocks('candidate', $candidate, $submission);

        if ($slug = $request->query('slug')) {
            $blocks = collect($blocks)->where('slug', $slug)->values()->all();
        }

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

        $submission = $this->builder->registrationSubmissionFor('candidate', $candidate->id, $agencyId);
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

        $dynamicRules = $this->builder->dynamicValidationRules($schema);

        // Hard-coded basic-information rules win if a schema field happens
        // to reuse one of those reserved names.
        $data = $request->validate(array_merge($dynamicRules, $basicRules));

        $basicData = array_intersect_key($data, array_flip(self::BASIC_INFORMATION_KEYS));
        $dynamicData = array_diff_key($data, array_flip(self::BASIC_INFORMATION_KEYS));
        $dynamicData = $this->builder->storeDynamicFileAnswers(
            $request, 'candidate', $schema, $dynamicData, (array) ($submission?->data ?? [])
        );

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

    // DELETE /agency/candidates/{id}/profile/documents/{key}
    public function destroyDocument(Request $request, $id, string $key)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        $submission = $this->builder->registrationSubmissionFor('candidate', $candidate->id, $agencyId);
        $schema = $submission?->form?->schema ?? ['blocks' => []];

        $field = collect($this->builder->flattenFields($schema))->firstWhere('name', $key);

        if (! $field || ! in_array($field['type'] ?? null, ['file_upload', 'list_files'], true)) {
            return response()->json(['status' => false, 'message' => 'Document field not found.'], 404);
        }

        $data = (array) ($submission?->data ?? []);
        $current = $data[$key] ?? null;

        if (! $current) {
            return response()->json(['status' => false, 'message' => 'No file uploaded for this field.'], 404);
        }

        if ($field['type'] === 'list_files' && ($path = $request->input('path'))) {
            Storage::disk('public')->delete($path);
            $data[$key] = collect((array) $current)->reject(fn ($existing) => $existing === $path)->values()->all();
        } else {
            Storage::disk('public')->delete((array) $current);
            $data[$key] = $field['type'] === 'list_files' ? [] : null;
        }

        $submission->data = $data;
        $submission->save();

        $columnUpdates = $this->builder->mapAnswersToColumns(Candidate::class, 'candidate', $schema, [$key => $data[$key]]);

        if ($columnUpdates) {
            $candidate->update($columnUpdates);
        }

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully',
        ]);
    }
}
