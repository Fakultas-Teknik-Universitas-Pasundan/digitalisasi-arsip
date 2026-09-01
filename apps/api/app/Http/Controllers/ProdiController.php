<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Requests\StoreProdiRequest;
use App\Http\Resources\ProdiResource;
use App\Models\AuditLog;
use App\Models\Prodi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProdiController extends Controller
{
    /**
     * Display a listing of active prodis.
     */
    #[OA\Get(
        path: '/api/prodis',
        summary: 'Daftar program studi aktif (UC-15)',
        description: 'Mengambil seluruh daftar program studi aktif di lingkungan fakultas.',
        security: [['cookieAuth' => []]],
        tags: ['Master Data'],
        responses: [
            new OA\Response(response: 200, description: 'Daftar program studi berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/ProdiListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $prodis = Prodi::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'message' => 'Daftar program studi berhasil diambil.',
            'status' => 'success',
            'data' => ProdiResource::collection($prodis),
        ]);
    }

    /**
     * Store a newly created prodi (Manager only).
     */
    #[OA\Post(
        path: '/api/prodis',
        summary: 'Tambah program studi baru (UC-15) - Manager only',
        description: 'Menambahkan data program studi baru ke database master data. Hanya dapat diakses oleh Manager.',
        security: [['cookieAuth' => []]],
        tags: ['Master Data'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Data program studi baru',
            content: new OA\JsonContent(ref: '#/components/schemas/CreateProdiRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Program studi berhasil ditambahkan', content: new OA\JsonContent(ref: '#/components/schemas/ProdiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden (Bukan Manager)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreProdiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $prodi = Prodi::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'degree' => strtoupper($validated['degree']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Audit log for prodi creation
        AuditLog::log(
            action: AuditAction::CREATE_PRODI->value,
            description: "Program Studi '{$prodi->name}' ({$prodi->code}) berhasil ditambahkan.",
            metadata: [
                'prodi_id' => $prodi->id,
                'code' => $prodi->code,
                'name' => $prodi->name,
                'degree' => $prodi->degree,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Program Studi berhasil ditambahkan.',
            'data' => new ProdiResource($prodi),
        ], 201);
    }
}
