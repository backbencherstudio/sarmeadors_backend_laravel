<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CandidateProfileController extends Controller
{
    // GET /agency/candidates/{id}/profile
    public function show($id)
    {
        $agencyId = auth('api')->user()->agency_id;

        $candidate = Candidate::where('agency_id', $agencyId)->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'personal_information' => [
                    'first_name' => $candidate->first_name,
                    'last_name' => $candidate->last_name,
                    'email' => $candidate->email,
                    'date_of_birth' => $candidate->date_of_birth?->toDateString(),
                    'nationality' => $candidate->nationality,
                    'phone_number' => $candidate->mobile,
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
                'additional_information' => [
                    'interested_in_iowa' => $candidate->interested_in_iowa,
                    'years_of_experience' => $candidate->years_of_experience,
                    'commitment' => $candidate->commitment,
                    'available_for' => $candidate->available_for,
                    'drivers_license' => $candidate->drivers_license,
                    'cpr_first_aid' => $candidate->cpr_first_aid,
                    'vaccinations' => $candidate->vaccinations,
                    'ok_with_pets' => $candidate->ok_with_pets,
                    'ok_with_travel' => $candidate->ok_with_travel,
                    'work_legally_in_us' => $candidate->work_legally_in_us,
                    'comfortable_paid_legally' => $candidate->comfortable_paid_legally,
                    'has_ssn' => $candidate->has_ssn,
                ],
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
