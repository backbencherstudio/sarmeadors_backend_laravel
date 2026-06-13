<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Password needs to be 8 characters and contain at least
     * one letter and one number (per the profile screen).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/'],
        ];
    }
}
