<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeleteProfileRequest;
use App\Http\Requests\Client\UpdatePasswordRequest;
use App\Models\Client;
use App\Services\FormBuilderService;
use App\Traits\ResolvesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientProfileController extends Controller
{
    use ResolvesClient;

    /**
     * Base field keys handled as direct `clients` table columns rather
     * than dynamic schema answers - kept in sync with
     * FormBuilderService::baseFields('client'), minus `password` and
     * `email` (a client can't change their login email here).
     *
     * @var array<int, string>
     */
    private const BASIC_INFORMATION_KEYS = [
        'first_name', 'last_name', 'image', 'type_id', 'location_id',
        'mobile', 'nationality', 'street_address', 'city', 'province', 'postal_code', 'country',
    ];

    public function __construct(private FormBuilderService $builder) {}

    // GET /client/profile
    public function show(Request $request): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        $submission = $this->builder->registrationSubmissionFor('client', $client->id, $request->current_agency->id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $client->id,
                'form_id' => $submission?->form_id,
                'form_name' => $submission?->form?->name,
                'blocks' => $this->builder->profileBlockSummaries(
                    'client', $client, $submission, $request->query('slug')
                ),
            ],
        ]);
    }

    // POST /client/profile
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $client = $this->currentClientOrFail($request);
        $agencyId = $request->current_agency->id;

        $submission = $this->builder->registrationSubmissionFor('client', $client->id, $agencyId);
        $schema = $submission?->form?->schema ?? ['blocks' => []];

        $basicRules = [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'mobile' => 'sometimes|nullable|string|max:20',
            'image' => 'sometimes|nullable|image|max:5120',
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
            'nationality' => 'sometimes|nullable|string|max:255',
            'street_address' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'province' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:255',
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

        $userUpdate = array_intersect_key($basicData, array_flip(['first_name', 'last_name', 'mobile', 'image']));
        if ($userUpdate) {
            $user->update($userUpdate);
        }

        $client->refresh();
        $submission?->refresh();

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

    // PUT /client/profile/password
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->sendError('Current password is incorrect.', [], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return $this->sendResponse([], 'Password updated successfully.', 200);
    }

    // DELETE /client/profile
    public function destroy(DeleteProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $client = $this->currentClientOrFail($request);

        if (! Hash::check($request->validated()['password'], $user->password)) {
            return $this->sendError('Password is incorrect.', [], 422);
        }

        $client->delete();
        $user->delete();

        return $this->sendResponse([], 'Account deleted successfully.', 200);
    }
}
