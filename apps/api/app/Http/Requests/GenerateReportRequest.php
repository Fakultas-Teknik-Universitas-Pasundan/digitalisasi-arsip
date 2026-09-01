<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GenerateReportRequest',
    required: ['period_start', 'period_end', 'format', 'type'],
    properties: [
        new OA\Property(property: 'period_start', type: 'string', format: 'date', example: '2026-01-01'),
        new OA\Property(property: 'period_end', type: 'string', format: 'date', example: '2026-08-21'),
        new OA\Property(property: 'format', type: 'string', enum: ['pdf', 'xlsx', 'csv'], example: 'pdf'),
        new OA\Property(property: 'type', type: 'string', enum: ['monthly', 'annual', 'custom'], example: 'monthly'),
        new OA\Property(property: 'style', type: 'string', enum: ['detailed', 'summary', 'executive'], example: 'detailed', nullable: true),
        new OA\Property(
            property: 'content',
            type: 'array',
            items: new OA\Items(type: 'string', enum: ['upload_stats', 'qc_metrics', 'doc_status', 'user_activity', 'trend_analysis']),
            nullable: true
        ),
    ]
)]
class GenerateReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by policy in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'format' => ['required', 'in:pdf,xlsx,csv'],
            'type' => ['required', 'in:monthly,annual,custom'],
            'style' => ['nullable', 'in:detailed,summary,executive'],
            'content' => ['nullable', 'array'],
            'content.*' => ['in:upload_stats,qc_metrics,doc_status,user_activity,trend_analysis'],
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
            'period_end.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'format.in' => 'Format laporan harus pdf, xlsx, atau csv.',
            'type.in' => 'Tipe laporan harus monthly, annual, atau custom.',
        ];
    }
}
