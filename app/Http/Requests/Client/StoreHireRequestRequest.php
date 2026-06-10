<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreHireRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Short-term hire requests post a private job for a specific candidate.
     * Long-term hires are handled through the interview flow, so only the
     * job type is required for those.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_type' => ['required', 'in:short-term,long-term'],
            'note' => ['nullable', 'string', 'max:1000'],

            // Job details (short-term only)
            'title' => ['required_if:job_type,short-term', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'compensation_amount' => ['required_if:job_type,short-term', 'numeric', 'min:0'],
            'compensation_currency' => ['nullable', 'string', 'size:3'],
            'compensation_type' => ['nullable', 'in:per_hour,per_day,per_week,flat'],

            // Job address (short-term only)
            'job_address' => ['required_if:job_type,short-term', 'string', 'max:500'],
            'home_city' => ['required_if:job_type,short-term', 'string', 'max:100'],
            'home_province' => ['required_if:job_type,short-term', 'string', 'max:100'],
            'home_postal_code' => ['required_if:job_type,short-term', 'string', 'max:20'],
            'country' => ['required_if:job_type,short-term', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],

            // Booking dates (short-term only)
            'dates' => ['required_if:job_type,short-term', 'array', 'min:1'],
            'dates.*.booking_date' => ['required', 'date'],
            'dates.*.start_time' => ['required', 'date_format:H:i'],
            'dates.*.end_time' => ['required', 'date_format:H:i', 'after:dates.*.start_time'],
        ];
    }
}
