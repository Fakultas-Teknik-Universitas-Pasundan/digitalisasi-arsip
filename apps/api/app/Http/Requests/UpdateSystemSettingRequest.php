<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
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
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'exists:system_settings,key'],
            'settings.*.value' => ['required', 'string'],
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
            'settings.required' => 'Pengaturan sistem wajib dikirim.',
            'settings.array' => 'Format pengaturan harus berupa array.',
            'settings.*.key.required' => 'Key pengaturan wajib diisi.',
            'settings.*.key.exists' => 'Kunci pengaturan tidak valid atau tidak ditemukan dalam sistem.',
            'settings.*.value.required' => 'Nilai pengaturan wajib diisi.',
        ];
    }
}
