<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BulkActionRequest',
    required: ['ids'],
    properties: [
        new OA\Property(
            property: 'ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2, 3],
            description: 'Array of resource IDs'
        ),
    ]
)]
class BulkActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by policies in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Daftar ID harus diisi.',
            'ids.array' => 'Daftar ID harus berupa array.',
            'ids.*.integer' => 'Setiap ID harus berupa angka.',
        ];
    }
}
