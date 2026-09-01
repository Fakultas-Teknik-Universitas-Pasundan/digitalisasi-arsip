<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkActionRequest;
use App\Models\Document;
use App\Enums\AuditAction;
use App\Enums\ModelType;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use OpenApi\Attributes as OA;

class DocumentDownloadController extends Controller
{
    /**
     * Download the specified document.
     */
    #[OA\Get(
        path: '/api/documents/{id}/download',
        operationId: 'downloadDocument',
        summary: 'Download Document (UC-10)',
        description: "Download file dokumen.\n\n**Aturan akses download:**\n- Dokumen **terverifikasi**: semua role bisa download\n- Dokumen **belum terverifikasi**:\n  - Manager: bisa download semua dokumen\n  - Uploader: hanya dokumen miliknya sendiri\n  - QC: hanya dokumen pending (untuk verifikasi)\n  - SBAP: tidak bisa download",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File PDF', content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
        ]
    )]
    public function download(Document $document)
    {
        $this->authorize('download', $document);

        if (!Storage::exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        AuditLog::log(
            action: AuditAction::DOWNLOAD_DOCUMENT->value,
            description: "Dokumen '{$document->file_name}' diunduh.",
            metadata: [
                'document_id' => $document->id,
                'document_type' => $document->document_type->value,
                'file_name' => $document->file_name,
                'prodi' => $document->prodi->value,
            ],
            modelType: ModelType::DOCUMENT->value,
            modelId: $document->id
        );

        return Storage::download(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Download multiple documents as ZIP
     */
    #[OA\Post(
        path: '/api/documents/download-multiple',
        operationId: 'downloadMultipleDocuments',
        summary: 'Download Multiple Documents (ZIP)',
        description: "Download beberapa dokumen sekaligus dalam format ZIP.\n\nAkses download sesuai dengan policy:\n- Manager: semua dokumen\n- Uploader: dokumen milik sendiri\n- QC: dokumen pending (untuk verifikasi)\n- SBAP: dokumen terverifikasi saja",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DeleteMultipleRequest')),
        responses: [
            new OA\Response(response: 200, description: 'File ZIP berisi dokumen', content: new OA\MediaType(mediaType: 'application/zip', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function downloadMultiple(BulkActionRequest $request)
    {
        $request->validate([
            'ids.*' => 'exists:documents,id',
        ]);

        $documentIds = $request->input('ids');
        $documents = Document::whereIn('id', $documentIds)->get();

        foreach ($documents as $document) {
            $this->authorize('download', $document);
        }

        $zipFileName = 'documents_' . now()->format('YmdHis') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat file ZIP');
        }

        foreach ($documents as $document) {
            $filePath = Storage::path($document->file_path);

            if (file_exists($filePath)) {
                $fileName = $document->file_name;
                $counter = 1;

                while ($zip->locateName($fileName) !== false) {
                    $pathInfo = pathinfo($document->file_name);
                    $fileName = $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
                    $counter++;
                }

                $zip->addFile($filePath, $fileName);
            }
        }

        $zip->close();

        AuditLog::log(
            action: AuditAction::DOWNLOAD_DOCUMENT->value,
            description: "Mengunduh " . count($documents) . " dokumen sebagai ZIP.",
            metadata: [
                'document_ids' => $documentIds,
                'total_files' => count($documents),
            ]
        );

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * View the specified document inline (PDF Viewer).
     */
    #[OA\Get(
        path: '/api/documents/{id}/view',
        operationId: 'viewDocument',
        summary: 'View Document Inline (PDF Viewer)',
        description: "Menampilkan file dokumen secara inline (untuk PDF viewer di browser).\n\nSemua user yang terautentikasi dapat melihat dokumen.",
        security: [['cookieAuth' => []]],
        tags: ['Documents'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Document ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File PDF inline', content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
        ]
    )]
    public function view(Document $document)
    {
        $this->authorize('view', $document);

        if (!Storage::exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        AuditLog::log(
            action: AuditAction::VIEW_DOCUMENT->value,
            description: "Dokumen '{$document->file_name}' dilihat.",
            metadata: [
                'document_id' => $document->id,
                'document_type' => $document->document_type->value,
                'file_name' => $document->file_name,
                'prodi' => $document->prodi->value,
            ],
            modelType: ModelType::DOCUMENT->value,
            modelId: $document->id
        );

        return Storage::response(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
            ]
        );
    }
}
