<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\DeleteCandidateAccountRequest;
use App\Http\Requests\Candidate\UpdateCandidatePasswordRequest;
use App\Http\Requests\Candidate\UpdateCandidateProfileRequest;
use App\Http\Resources\Candidate\CandidateProfileResource;
use App\Traits\ResolvesCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CandidateProfileController extends Controller
{
    use ResolvesCandidate;

    // GET /candidate/profile
    public function show(Request $request): JsonResponse
    {
        $candidate = $this->currentCandidateOrFail($request);

        return $this->sendResponse(new CandidateProfileResource($candidate), 'Profile retrieved successfully.', 200);
    }

    // PUT /candidate/profile
    public function update(UpdateCandidateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $candidate = $this->currentCandidateOrFail($request);

        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $imagePath = $request->file('image')->store('profiles', 'public');
            $user->update(['image' => $imagePath]);
            $candidate->update(['image' => $imagePath]);
        }

        $user->update(collect($validated)->only(['first_name', 'last_name', 'mobile'])->toArray());

        $candidateFields = [
            'first_name', 'last_name', 'mobile', 'location_id', 'type_id',
            'date_of_birth', 'nationality', 'street_address', 'city', 'province',
            'postal_code', 'country', 'hours_per_week', 'bilingual', 'pay_range_per_hour',
            'start_date', 'last_position_end_reason', 'reference_first_name', 'reference_last_name',
            'reference_phone', 'reference_email', 'reference_relation', 'reference_description',
            'interested_in_iowa', 'years_of_experience', 'commitment', 'available_for',
            'drivers_license', 'cpr_first_aid', 'vaccinations', 'ok_with_pets',
            'ok_with_travel', 'work_legally_in_us', 'comfortable_paid_legally', 'has_ssn', 'hear_about_us',
        ];

        $candidate->update(collect($validated)->only($candidateFields)->toArray());

        return $this->sendResponse(new CandidateProfileResource($candidate->fresh()), 'Profile updated successfully.', 200);
    }

    // PUT /candidate/profile/password
    public function updatePassword(UpdateCandidatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->sendError('Current password is incorrect.', [], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return $this->sendResponse([], 'Password updated successfully.', 200);
    }

    // DELETE /candidate/profile
    public function destroy(DeleteCandidateAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $candidate = $this->currentCandidateOrFail($request);

        if (! Hash::check($request->validated()['password'], $user->password)) {
            return $this->sendError('Password is incorrect.', [], 422);
        }

        $candidate->delete();
        $user->tokens()->delete();
        $user->delete();

        return $this->sendResponse([], 'Account deleted successfully.', 200);
    }
}
