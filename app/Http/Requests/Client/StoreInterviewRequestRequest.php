<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterviewRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only long-term hires schedule an interview, so the interview fields are
     * required for long-term and ignored for short-term (which uses the hire
     * request flow with booking dates, job details, and address instead).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_type' => ['required', 'in:short-term,long-term'],
            'interview_type' => ['required_if:job_type,long-term', 'in:in_person,zoom,google_meet'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_date' => ['required_if:job_type,long-term', 'date', 'after_or_equal:today'],
            'available_from' => ['required_if:job_type,long-term', 'date_format:H:i'],
            'available_to' => ['required_if:job_type,long-term', 'date_format:H:i', 'after:available_from'],
            'special_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
