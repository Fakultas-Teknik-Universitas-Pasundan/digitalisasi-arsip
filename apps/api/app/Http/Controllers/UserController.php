<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the users.
     */
    #[OA\Get(
        path: '/api/users',
        operationId: 'listUsers',
        summary: 'List Users',
        description: 'Mengambil daftar semua user (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Cari berdasarkan nama atau email', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', description: 'Filter berdasarkan role', schema: new OA\Schema(type: 'string', enum: ['manager', 'uploader', 'qc', 'sbap'])),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Jumlah data per halaman', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Nomor halaman', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar user berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/UserListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $users = $this->userService->listUsers($request->all(), $perPage);

        return response()->json([
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 200);
    }

    /**
     * Store a newly created user in storage.
     */
    #[OA\Post(
        path: '/api/users',
        operationId: 'createUser',
        summary: 'Create User',
        description: 'Membuat user baru (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateUserRequest')),
        responses: [
            new OA\Response(response: 201, description: 'User berhasil dibuat', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->createUser($request->validated());

        return response()->json([
            'message' => 'Pengguna berhasil dibuat.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user.
     */
    #[OA\Get(
        path: '/api/users/{id}',
        operationId: 'getUser',
        summary: 'Get User Detail',
        description: 'Mengambil detail user berdasarkan ID',
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail user berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'message' => 'Detail pengguna berhasil diambil.',
            'data' => new UserResource($user),
        ], 200);
    }

    /**
     * Update the specified user in storage.
     */
    #[OA\Put(
        path: '/api/users/{id}',
        operationId: 'updateUser',
        summary: 'Update User',
        description: 'Mengupdate data user',
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        responses: [
            new OA\Response(response: 200, description: 'User berhasil diupdate', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    #[OA\Patch(
        path: '/api/users/{id}',
        operationId: 'patchUser',
        summary: 'Update User (Partial)',
        description: "Mengupdate data user secara partial (hanya field yang dikirim yang akan diupdate).\n\nSemua field bersifat optional. Kirim hanya field yang ingin diubah.",
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        responses: [
            new OA\Response(response: 200, description: 'User berhasil diupdate', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
            new OA\Response(response: 422, description: 'Validation Error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updatedUser = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => new UserResource($updatedUser),
        ], 200);
    }

    /**
     * Remove the specified user from storage.
     */
    #[OA\Delete(
        path: '/api/users/{id}',
        operationId: 'deleteUser',
        summary: 'Delete User',
        description: 'Menghapus user (tidak bisa menghapus akun sendiri)',
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User berhasil dihapus',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Pengguna berhasil dihapus.')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Not Found.')])),
            new OA\Response(
                response: 422,
                description: 'Tidak dapat menghapus akun sendiri',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tidak dapat menghapus akun sendiri.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user_id', type: 'array', items: new OA\Items(type: 'string'), example: ['Anda tidak dapat menghapus akun Anda sendiri.']),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function destroy(User $user): JsonResponse
    {
        // Authorize first — non-managers get 403 before any other check
        $this->authorize('delete', $user);

        // Check if trying to delete self (policy also checks this,
        // but we return a descriptive 422 instead of generic 403)
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun sendiri.',
                'errors' => [
                    'user_id' => ['Anda tidak dapat menghapus akun Anda sendiri.'],
                ],
            ], 422);
        }

        $this->userService->deleteUser($user);

        return response()->json([
            'message' => 'Pengguna berhasil dihapus.',
        ], 200);
    }

    /**
     * Delete multiple users.
     */
    #[OA\Post(
        path: '/api/users/delete-multiple',
        operationId: 'deleteMultipleUsers',
        summary: 'Delete Multiple Users',
        description: 'Menghapus beberapa user sekaligus (Manager only). Tidak bisa menghapus akun sendiri.',
        security: [['cookieAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DeleteMultipleRequest')),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Users berhasil dihapus',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '3 pengguna berhasil dihapus.'),
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
            'ids.*' => 'exists:users,id',
        ]);

        $userIds = $request->input('ids');

        // Prevent self-deletion in bulk operation
        if (in_array(auth()->id(), $userIds)) {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun sendiri.',
                'errors' => [
                    'ids' => ['Anda tidak dapat menyertakan akun Anda sendiri dalam penghapusan massal.'],
                ],
            ], 422);
        }

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->authorize('delete', $user);
        }

        foreach ($users as $user) {
            $this->userService->deleteUser($user);
        }

        return response()->json([
            'message' => count($users) . ' pengguna berhasil dihapus.',
            'deleted_count' => count($users),
        ], 200);
    }

    /**
     * Get user statistics.
     */
    #[OA\Get(
        path: '/api/users/statistics',
        operationId: 'getUserStatistics',
        summary: 'Get User Statistics',
        description: 'Mengambil statistik pengguna: total, active, new users (Manager only)',
        security: [['cookieAuth' => []]],
        tags: ['Statistics'],
        responses: [
            new OA\Response(response: 200, description: 'Statistik pengguna berhasil diambil', content: new OA\JsonContent(ref: '#/components/schemas/UserStatisticsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')])),
        ]
    )]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $stats = $this->userService->getStatistics();

        return response()->json([
            'message' => 'Statistik pengguna berhasil diambil.',
            'data' => $stats,
        ], 200);
    }
}
