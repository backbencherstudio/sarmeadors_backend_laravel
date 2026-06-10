<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
        ];
    }
}
