<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeleteProfileRequest;
use App\Http\Requests\Client\UpdatePasswordRequest;
use App\Http\Requests\Client\UpdateProfileRequest;
use App\Http\Resources\Client\ClientProfileResource;
use App\Traits\ResolvesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ClientProfileController extends Controller
{
    use ResolvesClient;

    // GET /client/profile
    public function show(Request $request): JsonResponse
    {
        $client = $this->currentClientOrFail($request);

        return $this->sendResponse(
            new ClientProfileResource($client, $request->user()),
            'Profile retrieved successfully.',
            200
        );
    }

    // PUT /client/profile
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $client = $this->currentClientOrFail($request);

        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($user->getRawOriginal('image')) {
                Storage::disk('public')->delete($user->getRawOriginal('image'));
            }

            $imagePath = $request->file('image')->store('profiles', 'public');
            $user->update(['image' => $imagePath]);
            $client->update(['image' => $imagePath]);
        }

        $user->update(collect($validated)->only(['first_name', 'last_name', 'mobile'])->toArray());
        $client->update(collect($validated)->only(['first_name', 'last_name', 'mobile', 'location_id'])->toArray());

        return $this->sendResponse(
            new ClientProfileResource($client->fresh(), $user->fresh()),
            'Profile updated successfully.',
            200
        );
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
