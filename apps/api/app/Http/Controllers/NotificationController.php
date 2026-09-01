<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of notifications for the authenticated user.
     */
    #[OA\Get(
        path: '/api/notifications',
        summary: 'Daftar notifikasi pengguna yang sedang login (UC-13)',
        description: 'Mengambil daftar notifikasi milik pengguna yang terautentikasi dengan paginasi dan filter unread.',
        security: [['cookieAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'unread_only', in: 'query', description: 'Filter hanya notifikasi yang belum dibaca (true/false)', required: false, schema: new OA\Schema(type: 'boolean', example: false)),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Jumlah item per halaman (default 15)', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Nomor halaman', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar notifikasi berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/NotificationListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 100);

        $notifications = $this->notificationService->getUserNotifications(
            $user->id,
            $request->boolean('unread_only'),
            $perPage
        );

        return response()->json([
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->notificationService->getUnreadCount($user->id),
            ],
        ]);
    }

    /**
     * Get unread notifications count for the navbar badge.
     */
    #[OA\Get(
        path: '/api/notifications/unread-count',
        summary: 'Jumlah notifikasi belum dibaca (UC-13)',
        description: 'Mengambil jumlah total notifikasi yang belum dibaca untuk badge navbar.',
        security: [['cookieAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Jumlah unread notifikasi berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/NotificationUnreadCountResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user()->id);

        return response()->json([
            'message' => 'Jumlah notifikasi belum dibaca berhasil diambil.',
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    #[OA\Patch(
        path: '/api/notifications/{id}/read',
        summary: 'Tandai satu notifikasi sebagai sudah dibaca (UC-13)',
        description: 'Memperbarui status notifikasi menjadi sudah dibaca (read_at = now).',
        security: [['cookieAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'UUID Notifikasi', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notifikasi berhasil ditandai sebagai sudah dibaca', content: new OA\JsonContent(ref: '#/components/schemas/NotificationResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Notifikasi tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user()->id, $id);

        return response()->json([
            'message' => 'Notifikasi berhasil ditandai sebagai sudah dibaca.',
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark all notifications of the user as read.
     */
    #[OA\Post(
        path: '/api/notifications/mark-all-read',
        summary: 'Tandai semua notifikasi pengguna sebagai sudah dibaca (UC-13)',
        description: 'Memperbarui semua notifikasi yang belum dibaca milik pengguna menjadi sudah dibaca.',
        security: [['cookieAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Semua notifikasi berhasil ditandai sudah dibaca', content: new OA\JsonContent(ref: '#/components/schemas/SuccessMessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json([
            'message' => 'Semua notifikasi berhasil ditandai sebagai sudah dibaca.',
        ]);
    }

    /**
     * Remove the specified notification.
     */
    #[OA\Delete(
        path: '/api/notifications/{id}',
        summary: 'Hapus notifikasi (UC-13)',
        description: 'Menghapus satu notifikasi milik pengguna berdasarkan ID UUID.',
        security: [['cookieAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'UUID Notifikasi', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notifikasi berhasil dihapus', content: new OA\JsonContent(ref: '#/components/schemas/SuccessMessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Notifikasi tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->notificationService->deleteNotification($request->user()->id, $id);

        return response()->json([
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }
}
