<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SignDocumentRequest extends FormRequest
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
            'signature' => ['required', 'string', 'max:255'],
            'agree' => ['required', 'boolean', 'accepted'],
        ];
    }
}
