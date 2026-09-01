<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ModelType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        // Password will be auto-hashed by User model's 'hashed' cast
        $user = User::create($data);

        // Log activity
        AuditLog::log(
            action: AuditAction::CREATE_USER->value,
            description: "Pengguna {$user->name} ({$user->email}) dibuat dengan role {$user->role->value}.",
            metadata: [
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'role' => $user->role->value,
            ],
            modelType: ModelType::USER->value,
            modelId: $user->id
        );

        return $user;
    }

    /**
     * Update an existing user.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateUser(User $user, array $data): User
    {
        // Track changed fields
        $changedFields = [];
        $oldValues = [];

        // Check password change
        if (isset($data['password']) && !empty($data['password'])) {
            // Password will be auto-hashed by User model's 'hashed' cast
            $changedFields[] = 'password';
        } else {
            unset($data['password']);
        }

        // Track other changes
        foreach (['name', 'email', 'role'] as $field) {
            if (isset($data[$field]) && $data[$field] !== $user->{$field}) {
                $changedFields[] = $field;
                $oldValues[$field] = $user->{$field};
            }
        }

        $oldRole = $user->role;

        // Update user
        $user->update($data);

        // Log activity
        $metadata = [
            'target_user_id' => $user->id,
            'changed_fields' => $changedFields,
        ];

        if (in_array('role', $changedFields)) {
            $metadata['old_role'] = $oldRole->value;
            $metadata['new_role'] = $user->role->value;
        }

        if (!empty($oldValues)) {
            $metadata['old_values'] = $oldValues;
        }

        $description = "Pengguna {$user->name} ({$user->email}) diperbarui";
        if (!empty($changedFields)) {
            $description .= " - kolom yang diubah: " . implode(', ', $changedFields);
        }

        AuditLog::log(
            action: AuditAction::UPDATE_USER->value,
            description: $description,
            metadata: $metadata,
            modelType: ModelType::USER->value,
            modelId: $user->id
        );

        return $user->fresh();
    }

    /**
     * Delete a user.
     *
     * @param User $user
     * @return bool
     */
    public function deleteUser(User $user): bool
    {
        $userId = $user->id;
        $userName = $user->name;
        $userEmail = $user->email;
        $userRole = $user->role;

        // Delete user
        $deleted = $user->delete();

        if ($deleted) {
            // Log activity
            AuditLog::log(
                action: AuditAction::DELETE_USER->value,
                description: "Pengguna {$userName} ({$userEmail}) dengan role {$userRole->value} dihapus.",
                metadata: [
                    'target_user_id' => $userId,
                    'target_email' => $userEmail,
                    'role' => $userRole->value,
                ],
                modelType: ModelType::USER->value,
                modelId: $userId
            );
        }

        return $deleted;
    }
    /**
     * Get user statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $totalUsers = User::count();

        // Active users: Users who have at least one audit log in the last 30 days
        $activeUsers = User::whereHas('auditLogs', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        })->count();

        $newUsers = User::where('created_at', '>=', now()->subDays(30))->count();

        return [
            'total_users' => $totalUsers,
            'total_by_role' => User::selectRaw('role, count(*) as count')->groupBy('role')->pluck('count', 'role'),
            'active_users' => $activeUsers,
            'new_users' => $newUsers,
        ];
    }

    /**
     * Get paginated list of users with optional filtering.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listUsers(array $filters = [], int $perPage = 15)
    {
        $query = User::query();

        // Search by name or email
        if (!empty($filters['search'])) {
            $search = addcslashes($filters['search'], '%_\\');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
