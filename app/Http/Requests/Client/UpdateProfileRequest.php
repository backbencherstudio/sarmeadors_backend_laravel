<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif', 'max:10240'],
            'location_id' => ['nullable', 'array'],
            'location_id.*' => [
                'integer',
                Rule::exists('locations', 'id')->where('agency_id', $this->current_agency?->id),
            ],
        ];
    }
}
