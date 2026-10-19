<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // halaman publik, tidak butuh login
    }

    public function rules(): array
    {
        return [
            'card_id' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_id.regex' => 'Format kartu tidak valid.',
        ];
    }
}