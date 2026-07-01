<?php

namespace App\Http\Requests\Agency;

use Illuminate\Foundation\Http\FormRequest;

class SetInterviewMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The agency confirms a request or approves/enters a reschedule: it issues
     * the link and may set/adjust the date/time. When adjusting the time, both
     * ends must be supplied together; otherwise the client's proposed slot (for
     * a reschedule) or the existing slot is kept.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'interview_link' => ['required', 'url', 'max:2048'],
            'interview_type' => ['nullable', 'in:in_person,zoom,google_meet'],
            'title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'special_note' => ['nullable', 'string', 'max:1000'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],
            'available_from' => ['nullable', 'required_with:available_to', 'date_format:H:i'],
            'available_to' => ['nullable', 'required_with:available_from', 'date_format:H:i', 'after:available_from'],
        ];
    }
}
