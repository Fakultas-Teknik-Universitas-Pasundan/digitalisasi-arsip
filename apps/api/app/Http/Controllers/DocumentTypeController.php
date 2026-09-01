<?php

namespace App\Http\Controllers;

use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of document types.
     */
    #[OA\Get(
        path: '/api/document-types',
        summary: 'Daftar jenis dokumen (UC-15)',
        description: 'Mengambil seluruh daftar tipe dokumen yang didukung sistem beserta informasi kebutuhan verifikasi.',
        security: [['cookieAuth' => []]],
        tags: ['Master Data'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar jenis dokumen berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/DocumentTypeListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $types = DocumentType::orderBy('id', 'asc')->get();

        return response()->json([
            'message' => 'Daftar jenis dokumen berhasil diambil.',
            'status' => 'success',
            'data' => DocumentTypeResource::collection($types),
        ]);
    }
}
