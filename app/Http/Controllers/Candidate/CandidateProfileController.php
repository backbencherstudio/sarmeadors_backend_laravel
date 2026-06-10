<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDocument;
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

            return $this->sendResponse($this->formatProfileResponse($user, $candidate), 'Profile retrieved successfully.', 200);
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
                // Basic
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'image' => 'nullable|image|max:5120',
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'type_id' => 'nullable|array',
                'type_id.*' => 'integer|exists:types,id',

                // Personal info
                'date_of_birth' => 'nullable|date',
                'nationality' => 'nullable|string|max:255',

                // Address
                'street_address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'province' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:255',

                // Professional info
                'hours_per_week' => 'nullable|string|max:255',
                'bilingual' => 'nullable|string|max:255',
                'pay_range_per_hour' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'last_position_end_reason' => 'nullable|string|max:2000',

                // Reference
                'reference_first_name' => 'nullable|string|max:255',
                'reference_last_name' => 'nullable|string|max:255',
                'reference_phone' => 'nullable|string|max:20',
                'reference_email' => 'nullable|email|max:255',
                'reference_relation' => 'nullable|string|max:255',
                'reference_description' => 'nullable|string|max:2000',

                // Additional info
                'interested_in_iowa' => 'nullable|boolean',
                'years_of_experience' => 'nullable|in:2-5,5-10,10+',
                'commitment' => 'nullable|in:long_term,short_term,temporary',
                'available_for' => 'nullable|array',
                'available_for.*' => 'in:full_time,part_time,come_and_go,live_in',
                'drivers_license' => 'nullable|in:dl_and_car,dl_only,neither',
                'cpr_first_aid' => 'nullable|in:yes,willing,no',
                'vaccinations' => 'nullable|in:yes,willing,no',
                'ok_with_pets' => 'nullable|in:dog,cat,neither',
                'ok_with_travel' => 'nullable|in:domestic,international,no_travel',
                'work_legally_in_us' => 'nullable|boolean',
                'comfortable_paid_legally' => 'nullable|boolean',
                'has_ssn' => 'nullable|boolean',
                'hear_about_us' => 'nullable|string|max:255',
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

            return $this->sendResponse($this->formatProfileResponse($user->fresh(), $candidate->fresh()), 'Profile updated successfully.', 200);
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

    private function formatProfileResponse($user, Candidate $candidate): array
    {
        return [
            'profile' => [
                'header' => $this->formatHeader($candidate),
                'personal_information' => [
                    'first_name' => $candidate->first_name,
                    'last_name' => $candidate->last_name,
                    'email' => $candidate->email,
                    'date_of_birth' => $candidate->date_of_birth?->toDateString(),
                    'nationality' => $candidate->nationality,
                    'mobile' => $candidate->mobile,
                    'address' => [
                        'street_address' => $candidate->street_address,
                        'city' => $candidate->city,
                        'province' => $candidate->province,
                        'postal_code' => $candidate->postal_code,
                        'country' => $candidate->country,
                    ],
                ],
                'professional_information' => [
                    'hours_per_week' => $candidate->hours_per_week,
                    'bilingual' => $candidate->bilingual,
                    'pay_range_per_hour' => $candidate->pay_range_per_hour,
                    'start_date' => $candidate->start_date?->toDateString(),
                    'last_position_end_reason' => $candidate->last_position_end_reason,
                ],
                'reference' => [
                    'first_name' => $candidate->reference_first_name,
                    'last_name' => $candidate->reference_last_name,
                    'phone' => $candidate->reference_phone,
                    'email' => $candidate->reference_email,
                    'relation' => $candidate->reference_relation,
                    'description' => $candidate->reference_description,
                ],
                'documents_summary' => $this->formatDocumentsSummary($candidate),
                'additional_information' => [
                    'interested_in_iowa' => $candidate->interested_in_iowa,
                    'years_of_experience' => $candidate->years_of_experience,
                    'commitment' => $candidate->commitment,
                    'available_for' => $candidate->available_for ?? [],
                    'drivers_license' => $candidate->drivers_license,
                    'cpr_first_aid' => $candidate->cpr_first_aid,
                    'vaccinations' => $candidate->vaccinations,
                    'ok_with_pets' => $candidate->ok_with_pets,
                    'ok_with_travel' => $candidate->ok_with_travel,
                    'work_legally_in_us' => $candidate->work_legally_in_us,
                    'comfortable_paid_legally' => $candidate->comfortable_paid_legally,
                    'has_ssn' => $candidate->has_ssn,
                    'hear_about_us' => $candidate->hear_about_us,
                ],
            ],
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'image' => $candidate->image_url,
            ],
            'candidate' => $candidate->append('image_url'),
        ];
    }

    private function formatHeader(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => trim($candidate->first_name.' '.$candidate->last_name),
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'email' => $candidate->email,
            'mobile' => $candidate->mobile,
            'image_url' => $candidate->image_url,
            'completion_percentage' => $this->calculateCompletionPercentage($candidate),
        ];
    }

    private function formatDocumentsSummary(Candidate $candidate): array
    {
        $documents = CandidateDocument::where('candidate_id', $candidate->id)
            ->where('agency_id', $candidate->agency_id)
            ->get();

        return [
            'total' => $documents->count(),
            'agreements_signed' => $documents->where('category', 'agreement')->where('status', 'signed')->count(),
            'required_uploaded' => $documents->where('category', 'required')->where('status', 'uploaded')->count(),
            'additional_uploaded' => $documents->where('category', 'additional')->where('status', 'uploaded')->count(),
        ];
    }

    private function calculateCompletionPercentage(Candidate $candidate): int
    {
        $fields = [
            'first_name',
            'last_name',
            'email',
            'mobile',
            'date_of_birth',
            'nationality',
            'street_address',
            'city',
            'province',
            'postal_code',
            'country',
            'hours_per_week',
            'bilingual',
            'pay_range_per_hour',
            'start_date',
            'reference_first_name',
            'reference_last_name',
            'reference_phone',
            'reference_email',
            'years_of_experience',
            'commitment',
            'available_for',
            'drivers_license',
            'cpr_first_aid',
            'vaccinations',
            'work_legally_in_us',
        ];

        $completed = collect($fields)
            ->filter(function (string $field) use ($candidate): bool {
                $value = $candidate->{$field};

                if (is_array($value)) {
                    return count($value) > 0;
                }

                return filled($value);
            })
            ->count();

        return (int) round(($completed / count($fields)) * 100);
    }
}
