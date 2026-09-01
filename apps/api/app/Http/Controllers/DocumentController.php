<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use App\Enums\DocumentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use OpenApi\Attributes as OA;

class DocumentController extends Controller
{
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of documents.
     */
    #[OA\Get(
        path: '/api/documents',
        operationId: 'listDocuments',
        summary: 'List Documents',
        description: 'Mengambil daftar dokumen',
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'document_type', in: 'query', description: 'Filter berdasarkan tipe dokumen', schema: new OA\Schema(type: 'string', enum: ['nilai', 'transkrip', 'ijazah', 'berita_acara_sidang'])),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter berdasarkan status', schema: new OA\Schema(type: 'string', enum: ['menunggu_verifikasi', 'terverifikasi', 'tidak_terverifikasi'])),
            new OA\Parameter(name: 'prodi', in: 'query', description: 'Filter berdasarkan program studi', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Cari berdasarkan nama file', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', description: 'Kolom untuk sorting (default: created_at)', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at', 'updated_at', 'tahun_lulus', 'status', 'document_type', 'prodi', 'file_name'])),
            new OA\Parameter(name: 'sort_direction', in: 'query', description: 'Arah sorting (asc/desc, default: desc)', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Jumlah data per halaman', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Nomor halaman', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar dokumen berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/DocumentListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $perPage = min((int) $request->input('per_page', 15), 100);

        // Uploader only sees their own documents (API Contract requirement)
        $user = auth()->user();
        $uploaderId = $user->hasRole('uploader') ? $user->id : null;

        $documents = $this->documentService->listDocuments($request->all(), $perPage, $uploaderId);

        return response()->json([
            'message' => 'Daftar dokumen berhasil diambil.',
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
     * Store a newly uploaded document.
     */
    #[OA\Post(
        path: '/api/documents',
        operationId: 'uploadDocument',
        summary: 'Upload Document (UC-04)',
        description: "Upload dokumen baru (Manager & Uploader only).\n\n**Tipe dokumen dan field yang diperlukan:**\n- `nilai`: prodi, tahun_ajaran, mata_kuliah, kelas\n- `ijazah` / `transkrip` / `berita_acara_sidang`: prodi, tahun_lulus, npm",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/UploadDocumentRequest')
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Dokumen berhasil diunggah', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(
                response: 409,
                description: 'Dokumen duplikat terdeteksi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dokumen duplikat terdeteksi.'),
                        new OA\Property(property: 'errors', type: 'object', properties: [
                            new OA\Property(property: 'duplicate', type: 'array', items: new OA\Items(type: 'string')),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        try {
            $data = $request->validatedData();
            $file = $request->file('file');
            $userId = auth()->id();

            $document = $this->documentService->uploadDocument($data, $file, $userId);

            return response()->json([
                'message' => 'Dokumen berhasil diunggah.',
                'data' => new DocumentResource($document),
            ], 201);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'message' => 'Dokumen duplikat terdeteksi.',
                'errors' => [
                    'duplicate' => [$e->getMessage()],
                ],
            ], 409);
        }
    }

    /**
     * Display the specified document.
     */
    #[OA\Get(
        path: '/api/documents/{id}',
        operationId: 'getDocument',
        summary: 'Get Document Detail',
        description: 'Mengambil detail dokumen berdasarkan ID',
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail dokumen berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
        ]
    )]
    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->load(['uploader', 'verifier']);

        return response()->json([
            'message' => 'Detail dokumen berhasil diambil.',
            'data' => new DocumentResource($document),
        ], 200);
    }

    /**
     * Update the specified document.
     */
    #[OA\Put(
        path: '/api/documents/{id}',
        operationId: 'updateDocument',
        summary: 'Update Document (UC-06)',
        description: "Update metadata dokumen (hanya untuk dokumen dengan status `tidak_terverifikasi`).\n\nSetelah update, status akan direset ke `menunggu_verifikasi`.\nFile dan document_type tidak dapat diubah.",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateDocumentRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Dokumen berhasil diperbarui', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
            new OA\Response(response: 422, description: 'Dokumen yang sudah terverifikasi tidak dapat diperbarui', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(
        UpdateDocumentRequest $request,
        Document $document
    ): JsonResponse {
        $this->authorize('update', $document);

        try {
            $updated = $this->documentService->updateDocument(
                $document,
                $request->validated(),
                auth()->id()
            );

            return response()->json([
                'message' => 'Dokumen berhasil diperbarui. Status direset ke menunggu verifikasi.',
                'data' => new DocumentResource($updated),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove the specified document.
     */
    #[OA\Delete(
        path: '/api/documents/{id}',
        operationId: 'deleteDocument',
        summary: 'Delete Document (UC-07)',
        description: "Menghapus dokumen (hanya untuk dokumen dengan status `tidak_terverifikasi`).\n\nManager dapat menghapus semua dokumen rejected.\nUploader hanya dapat menghapus dokumen miliknya sendiri.",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dokumen berhasil dihapus', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Dokumen berhasil dihapus.')])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Tidak memiliki izin atau dokumen sudah terverifikasi', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
        ]
    )]
    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->deleteDocument($document);

        return response()->json([
            'message' => 'Dokumen berhasil dihapus.',
        ], 200);
    }

    /**
     * Remove multiple documents.
     */
    #[OA\Post(
        path: '/api/documents/delete-multiple',
        operationId: 'deleteMultipleDocuments',
        summary: 'Delete Multiple Documents',
        description: "Menghapus beberapa dokumen sekaligus.\n\nHanya dokumen dengan status `tidak_terverifikasi` yang dapat dihapus.\nManager dapat menghapus semua dokumen rejected. Uploader hanya dokumen miliknya.",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DeleteMultipleRequest')),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dokumen berhasil dihapus',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 dokumen berhasil dihapus.'),
                        new OA\Property(property: 'deleted_count', type: 'integer', example: 3),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function destroyMultiple(BulkActionRequest $request): JsonResponse
    {
        $request->validate([
            'ids.*' => 'exists:documents,id',
        ]);

        $documentIds = $request->input('ids');
        $documents = Document::whereIn('id', $documentIds)->get();

        foreach ($documents as $document) {
            $this->authorize('delete', $document);
        }

        foreach ($documents as $document) {
            $this->documentService->deleteDocument($document);
        }

        return response()->json([
            'message' => count($documents) . ' dokumen berhasil dihapus.',
            'deleted_count' => count($documents),
        ], 200);
    }

    /**
     * Get document statistics.
     */
    #[OA\Get(
        path: '/api/documents/statistics',
        operationId: 'getDocumentStatistics',
        summary: 'Get Document Statistics',
        description: 'Mengambil statistik dokumen: total, verified, pending, rejected (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Statistics'],
        responses: [
            new OA\Response(response: 200, description: 'Statistik dokumen berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/DocumentStatisticsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $stats = $this->documentService->getStatistics();

        return response()->json([
            'message' => 'Statistik dokumen berhasil diambil.',
            'data' => $stats,
        ], 200);
    }

    /**
     * Display a listing of soft-deleted documents (Manager only).
     */
    #[OA\Get(
        path: '/api/documents/trashed',
        operationId: 'listTrashedDocuments',
        summary: 'List Trashed Documents',
        description: 'Mengambil daftar dokumen yang ada di tempat sampah (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar dokumen terhapus berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/DocumentListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('viewTrashed', Document::class);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $documents = $this->documentService->getTrashedDocuments($request->all(), $perPage);

        return response()->json([
            'message' => 'Daftar dokumen terhapus berhasil diambil.',
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
     * Restore a soft-deleted document (Manager only).
     */
    #[OA\Post(
        path: '/api/documents/{id}/restore',
        operationId: 'restoreDocument',
        summary: 'Restore Trashed Document',
        description: 'Memulihkan dokumen yang terhapus kembali ke status semula (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dokumen berhasil dipulihkan', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Dokumen tidak ditemukan.')])),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $document);

        $restored = $this->documentService->restoreDocument($id, auth()->id());

        return response()->json([
            'message' => 'Dokumen berhasil dipulihkan.',
            'data' => new DocumentResource($restored),
        ], 200);
    }

    /**
     * Permanently delete a document and its file (Manager only).
     */
    #[OA\Delete(
        path: '/api/documents/{id}/force-delete',
        operationId: 'forceDeleteDocument',
        summary: 'Force Delete Document',
        description: 'Menghapus dokumen dan file fisiknya secara permanen (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dokumen berhasil dihapus permanen', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Dokumen berhasil dihapus permanen.')])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Dokumen tidak ditemukan.')])),
        ]
    )]
    public function forceDestroy(int $id): JsonResponse
    {
        $document = Document::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $document);

        $this->documentService->forceDeleteDocument($document);

        return response()->json([
            'message' => 'Dokumen berhasil dihapus permanen.',
        ], 200);
    }
}

