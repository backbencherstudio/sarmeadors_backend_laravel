<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\FormBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientProfileController extends Controller
{
    /**
     * Base field keys handled as direct `clients` table columns rather
     * than dynamic schema answers - kept in sync with
     * FormBuilderService::baseFields('client'), minus `password`.
     *
     * @var array<int, string>
     */
    private const BASIC_INFORMATION_KEYS = ['first_name', 'last_name', 'email', 'image', 'type_id', 'location_id'];

    public function __construct(private FormBuilderService $builder) {}

    // GET /agency/clients/{id}/profile
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($id);

        $submission = $this->builder->registrationSubmissionFor('client', $client->id, $agencyId);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $client->id,
                'form_id' => $submission?->form_id,
                'form_name' => $submission?->form?->name,
                'blocks' => $this->builder->profileBlocks('client', $client, $submission),
            ],
        ]);
    }

    // PATCH /agency/clients/{id}/profile
    public function update(Request $request, $id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($id);

        $submission = $this->builder->registrationSubmissionFor('client', $client->id, $agencyId);
        $schema = $submission?->form?->schema ?? ['blocks' => []];

        $basicRules = [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('clients', 'email')->ignore($client->id)],
            'image' => 'sometimes|nullable|file|mimes:jpeg,jpg,png,gif|max:10240',
            'type_id' => 'sometimes|nullable|array',
            'type_id.*' => [
                'integer',
                Rule::exists('types', 'id')->where(fn ($q) => $q->where('agency_id', $agencyId)->where('type', 'client')),
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
            $request, 'client', $schema, $dynamicData, (array) ($submission?->data ?? [])
        );

        if ($request->hasFile('image')) {
            if ($client->image) {
                Storage::disk('public')->delete($client->image);
            }

            $basicData['image'] = $request->file('image')->store('clients', 'public');
        } else {
            unset($basicData['image']);
        }

        // Capture old email before the update so we can find the linked user
        $oldEmail = $client->email;

        if ($basicData) {
            $client->update($basicData);
        }

        if ($submission && $dynamicData) {
            $submission->data = array_merge((array) $submission->data, $dynamicData);
            $submission->save();

            $columnUpdates = $this->builder->mapAnswersToColumns(Client::class, 'client', $schema, $dynamicData);

            if ($columnUpdates) {
                $client->update($columnUpdates);
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

    // DELETE /agency/clients/{id}/profile/documents/{key}
    public function destroyDocument(Request $request, $id, string $key)
    {
        $agencyId = auth('api')->user()->agency_id;

        $client = Client::where('agency_id', $agencyId)->findOrFail($id);

        $submission = $this->builder->registrationSubmissionFor('client', $client->id, $agencyId);
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

        $columnUpdates = $this->builder->mapAnswersToColumns(Client::class, 'client', $schema, [$key => $data[$key]]);

        if ($columnUpdates) {
            $client->update($columnUpdates);
        }

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully',
        ]);
    }
}
