<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityRequest extends FormRequest
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
            'timezone' => 'sometimes|required|string|max:100',
            'days' => 'sometimes|required|array',
            'days.*.day_of_week' => 'required|integer|between:0,6',
            'days.*.is_available' => 'required|boolean',
            'days.*.start_time' => 'nullable|date_format:H:i',
            'days.*.end_time' => 'nullable|date_format:H:i',
        ];
    }
}
