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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_type' => ['required', 'in:short-term,long-term'],
            'interview_type' => ['required', 'in:in_person,zoom,google_meet'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'available_from' => ['required', 'date_format:H:i'],
            'available_to' => ['required', 'date_format:H:i', 'after:available_from'],
            'special_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
