<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'nis' => ['nullable', 'string', 'max:30', 'unique:students,nis'],
            'card_id' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/', 'unique:students,card_id'],
            'class_id' => ['required', 'exists:classes,id'],
            'is_active' => ['nullable', 'boolean'],
            'parent_name' => ['required', 'string', 'max:100'],
            'parent_relationship' => ['nullable', 'string', 'max:30'],
            'parent_wa_number' => ['required', 'regex:/^628[0-9]{8,13}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_id.regex' => 'Kode kartu hanya boleh huruf dan angka.',
            'parent_wa_number.regex' => 'Format nomor harus diawali 628, tanpa spasi atau tanda hubung.',
        ];
    }
}