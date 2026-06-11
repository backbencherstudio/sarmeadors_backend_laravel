<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleInterviewRequest extends FormRequest
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
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'available_from' => ['required', 'date_format:H:i'],
            'available_to' => ['required', 'date_format:H:i', 'after:available_from'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
