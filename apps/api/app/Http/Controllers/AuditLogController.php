<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Enums\AuditAction;
use App\Enums\ModelType;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     * Only accessible by Manager role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[OA\Get(
        path: '/api/audit-logs',
        operationId: 'listAuditLogs',
        summary: 'List Audit Logs (UC-03)',
        description: "Mengambil daftar audit log aktivitas sistem (Manager only).\n\nMendukung filtering berdasarkan action, user, model type, dan date range.",
        security: [['cookieAuth' => []]],
        tags: ['Audit Logs'],
        parameters: [
            new OA\Parameter(name: 'action', in: 'query', description: 'Filter berdasarkan action type', schema: new OA\Schema(type: 'string', example: 'upload_document')),
            new OA\Parameter(name: 'user_id', in: 'query', description: 'Filter berdasarkan user ID', schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'model_type', in: 'query', description: 'Filter berdasarkan model type', schema: new OA\Schema(type: 'string', example: 'App\\Models\\Document')),
            new OA\Parameter(name: 'start_date', in: 'query', description: 'Filter dari tanggal (ISO 8601)', schema: new OA\Schema(type: 'string', format: 'date-time', example: '2026-01-01T00:00:00Z')),
            new OA\Parameter(name: 'end_date', in: 'query', description: 'Filter sampai tanggal (ISO 8601)', schema: new OA\Schema(type: 'string', format: 'date-time', example: '2026-01-31T23:59:59Z')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Cari berdasarkan description', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Jumlah data per halaman', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Nomor halaman', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar audit log berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/AuditLogListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::with('user');

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by model type
        if ($request->has('model_type')) {
            $query->where('model_type', $request->input('model_type'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        // Search by description
        if ($request->has('search')) {
            $search = addcslashes($request->input('search'), '%_\\');
            $query->where('description', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 20), 100);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Log aktivitas berhasil diambil.',
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 200);
    }

    /**
     * Get statistics about audit logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[OA\Get(
        path: '/api/audit-logs/statistics',
        operationId: 'getAuditLogStatistics',
        summary: 'Get Audit Log Statistics (UC-03)',
        description: "Mengambil statistik aktivitas sistem (Manager only).\n\nTermasuk total aktivitas, breakdown by action, by user, dan recent activities.",
        security: [['cookieAuth' => []]],
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', description: 'Filter dari tanggal (default: 30 hari lalu)', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'end_date', in: 'query', description: 'Filter sampai tanggal (default: sekarang)', schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statistik berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/AuditLogStatisticsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        // Get date range (default: last 30 days)
        $startDate = $request->input('start_date', now()->subDays(30));
        $endDate = $request->input('end_date', now());

        $stats = [
            'total_activities' => AuditLog::whereBetween('created_at', [$startDate, $endDate])->count(),
            'today_total' => AuditLog::whereDate('created_at', today())->count(),
            'today_upload' => AuditLog::whereDate('created_at', today())
                ->where('action', AuditAction::UPLOAD_DOCUMENT->value)
                ->count(),
            'today_verify' => AuditLog::whereDate('created_at', today())
                ->where('action', AuditAction::VERIFY_DOCUMENT->value)
                ->count(),
            'today_reject' => AuditLog::whereDate('created_at', today())
                ->where('action', AuditAction::REJECT_DOCUMENT->value)
                ->count(),
            'by_action' => AuditLog::whereBetween('created_at', [$startDate, $endDate])
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->get()
                ->mapWithKeys(function ($item) {
                    $enum = AuditAction::tryFrom($item->action);
                    return [$enum?->label() ?? $item->action => $item->count];
                }),
            'recent_activities' => AuditLogResource::collection(
                AuditLog::with('user')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ),
        ];

        return response()->json([
            'message' => 'Statistik aktivitas berhasil diambil.',
            'data' => $stats,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ], 200);
    }

    /**
     * Export audit logs to CSV.
     *
     * @param Request $request
     * @return StreamedResponse
     */
    #[OA\Get(
        path: '/api/audit-logs/export',
        operationId: 'exportAuditLogs',
        summary: 'Export Audit Logs (UC-11)',
        description: 'Export audit logs ke format CSV (Manager only).',
        security: [['cookieAuth' => []]],
        tags: ['Audit Logs'],
        parameters: [
            new OA\Parameter(name: 'format', in: 'query', description: 'Format export (default: csv)', schema: new OA\Schema(type: 'string', default: 'csv', enum: ['csv'])),
            new OA\Parameter(name: 'start_date', in: 'query', description: 'Filter dari tanggal', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01')),
            new OA\Parameter(name: 'end_date', in: 'query', description: 'Filter sampai tanggal', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-12-31')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File CSV', content: new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $startDate = $request->input('start_date', now()->subDays(30));
        $endDate = $request->input('end_date', now());

        $query = AuditLog::with('user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Apply filters if present
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=audit_logs_" . date('Y-m-d_H-i') . ".csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['No', 'User', 'Role', 'Aksi', 'ID Dokumen', 'Nama Dokumen', 'Waktu', 'Tanggal', 'Deskripsi']);

            $index = 0;
            $query->orderBy('created_at', 'desc')->chunk(500, function ($logs) use ($file, &$index) {
                foreach ($logs as $log) {
                    $index++;
                    $actionEnum = AuditAction::tryFrom($log->action);

                    fputcsv($file, [
                        $index,
                        $log->user?->name ?? 'System',
                        $log->user?->role?->value ?? 'System',
                        $actionEnum?->label() ?? $log->action,
                        $log->model_type === ModelType::DOCUMENT->value ? 'DOC-' . $log->model_id : '-',
                        $log->metadata['file_name'] ?? '-',
                        $log->created_at->format('H.i'),
                        $log->created_at->format('d/m/Y'),
                        $log->description
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
