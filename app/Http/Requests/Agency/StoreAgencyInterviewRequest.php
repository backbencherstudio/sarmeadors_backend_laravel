<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Agency-initiated "Schedule Event": one confirmed interview is created per
     * selected candidate.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer', 'exists:candidates,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'available_from' => ['required', 'date_format:H:i'],
            'available_to' => ['required', 'date_format:H:i', 'after:available_from'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:255'],
            'interview_link' => ['nullable', 'url', 'max:2048'],
            'interview_type' => ['nullable', 'in:in_person,zoom,google_meet'],
            'description' => ['nullable', 'string', 'max:2000'],
            'special_note' => ['nullable', 'string', 'max:1000'],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => ['integer', 'exists:users,id'],
            'send_email' => ['nullable', 'boolean'],
        ];
    }
}
