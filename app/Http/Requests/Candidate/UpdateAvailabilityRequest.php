<?php

namespace App\Http\Requests\Candidate;

use App\Models\CandidateAvailabilityDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize day names to lowercase so input is case-insensitive.
     */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('days'))) {
            return;
        }

        $days = array_map(function ($day) {
            if (is_array($day) && isset($day['day']) && is_string($day['day'])) {
                $day['day'] = strtolower($day['day']);
            }

            return $day;
        }, $this->input('days'));

        $this->merge(['days' => $days]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timezone' => 'sometimes|required|string|max:100',
            'days' => 'sometimes|required|array',
            'days.*.day' => ['required', Rule::in(array_keys(CandidateAvailabilityDay::DAYS))],
            'days.*.is_available' => 'required|boolean',
            'days.*.from' => 'nullable|date_format:H:i',
            'days.*.to' => 'nullable|date_format:H:i',
        ];
    }
}
