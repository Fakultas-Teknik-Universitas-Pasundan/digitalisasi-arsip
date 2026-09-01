<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use App\Enums\DocumentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DocumentVerificationController extends Controller
{
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display pending documents awaiting verification.
     */
    #[OA\Get(
        path: '/api/documents/pending',
        operationId: 'listPendingDocuments',
        summary: 'List Pending Documents (UC-08)',
        description: 'Mengambil daftar dokumen yang menunggu verifikasi (Manager & QC only)',
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Jumlah data per halaman', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Nomor halaman', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar dokumen pending berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/DocumentListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function pending(Request $request): JsonResponse
    {
        $this->authorize('viewPending', Document::class);

        $query = Document::with(['uploader'])
            ->where('status', DocumentStatus::PENDING->value)
            ->orderBy('created_at', 'asc');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $documents = $query->paginate($perPage);

        return response()->json([
            'message' => 'Daftar dokumen menunggu verifikasi.',
            'data' => DocumentResource::collection($documents),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ], 200);
    }

    /**
     * Verify or reject a document.
     */
    #[OA\Patch(
        path: '/api/documents/{id}/verify',
        operationId: 'verifyDocument',
        summary: 'Verify Document (UC-08)',
        description: "Verifikasi atau tolak dokumen (Manager & QC only).\n\nDokumen yang sudah terverifikasi sebelumnya tidak dapat diverifikasi ulang.",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyDocumentRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Dokumen berhasil diverifikasi/ditolak', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
            new OA\Response(response: 422, description: 'Dokumen sudah diverifikasi sebelumnya', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function verify(
        VerifyDocumentRequest $request,
        Document $document
    ): JsonResponse {
        $this->authorize('verify', $document);

        try {
            $verified = $this->documentService->verifyDocument(
                $document,
                $request->input('status'),
                $request->input('verification_note'),
                auth()->id()
            );

            $message = $verified->status === DocumentStatus::VERIFIED
                ? 'Dokumen berhasil diverifikasi.'
                : 'Dokumen ditolak.';

            return response()->json([
                'message' => $message,
                'data' => new DocumentResource($verified),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
