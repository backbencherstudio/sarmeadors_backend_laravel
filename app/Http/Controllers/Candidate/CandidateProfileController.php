<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CandidateProfileController extends Controller
{
    private function resolveCandidate(Request $request): ?Candidate
    {
        return Candidate::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    // GET /candidate/profile
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            return $this->sendResponse([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'image' => $user->image ? asset('storage/'.$user->image) : null,
                ],
                'candidate' => $candidate->append('image_url'),
            ], 'Profile retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /candidate/profile
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'image' => 'nullable|image|max:5120',
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'type_id' => 'nullable|array',
                'type_id.*' => 'integer|exists:types,id',
            ]);

            if ($request->hasFile('image')) {
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $imagePath = $request->file('image')->store('profiles', 'public');
                $user->update(['image' => $imagePath]);
                $candidate->update(['image' => $imagePath]);
            }

            $user->update(collect($validated)->only(['first_name', 'last_name', 'mobile'])->toArray());

            $candidate->update(
                collect($validated)->only(['first_name', 'last_name', 'mobile', 'location_id', 'type_id'])->toArray()
            );

            return $this->sendResponse([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'image' => $user->image ? asset('storage/'.$user->image) : null,
                ],
                'candidate' => $candidate->fresh()->append('image_url'),
            ], 'Profile updated successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // PUT /candidate/profile/password
    public function updatePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/',
            ]);

            if (! Hash::check($validated['current_password'], $user->password)) {
                return $this->sendError('Current password is incorrect.', [], 422);
            }

            $user->update(['password' => Hash::make($validated['password'])]);

            return $this->sendResponse([], 'Password updated successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    // DELETE /candidate/profile
    public function destroy(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $candidate = $this->resolveCandidate($request);

            if (! $candidate) {
                return $this->sendError('Candidate profile not found.', [], 404);
            }

            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            if (! Hash::check($validated['password'], $user->password)) {
                return $this->sendError('Password is incorrect.', [], 422);
            }

            $candidate->delete();
            $user->tokens()->delete();
            $user->delete();

            return $this->sendResponse([], 'Account deleted successfully.', 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }
}
