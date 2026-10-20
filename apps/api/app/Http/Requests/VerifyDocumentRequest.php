<?php

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DocumentStatus::class), Rule::in([DocumentStatus::VERIFIED->value, DocumentStatus::REJECTED->value])],
            'verification_note' => ['nullable', 'string', 'max:500'],
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
            'status.required' => 'Status verifikasi wajib diisi.',
            'status.in' => 'Status harus terverifikasi atau tidak terverifikasi (tidak boleh pending).',
            'verification_note.max' => 'Catatan verifikasi maksimal 500 karakter.',
        ];
    }
}
