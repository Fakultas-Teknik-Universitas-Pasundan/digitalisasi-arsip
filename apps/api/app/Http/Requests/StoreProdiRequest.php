<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreProdiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(UserRole::MANAGER);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:prodis,code'],
            'name' => ['required', 'string', 'max:100'],
            'degree' => ['required', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode program studi wajib diisi.',
            'code.max' => 'Kode program studi maksimal 20 karakter.',
            'code.unique' => 'Kode program studi sudah terdaftar dalam sistem.',
            'name.required' => 'Nama program studi wajib diisi.',
            'name.max' => 'Nama program studi maksimal 100 karakter.',
            'degree.required' => 'Jenjang pendidikan (degree) wajib diisi.',
        ];
    }
}
