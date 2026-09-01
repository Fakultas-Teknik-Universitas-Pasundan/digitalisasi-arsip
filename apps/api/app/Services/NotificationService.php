<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Get paginated notifications for a user.
     *
     * @param int $userId
     * @param bool $unreadOnly
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserNotifications(int $userId, bool $unreadOnly = false, int $perPage = 15)
    {
        $query = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get unread notifications count for a user.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Mark a single notification as read.
     *
     * @param int $userId
     * @param string $notificationId
     * @return Notification
     */
    public function markAsRead(int $userId, string $notificationId): Notification
    {
        $notification = Notification::where('user_id', $userId)
            ->where('id', $notificationId)
            ->firstOrFail();

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param int $userId
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Delete a notification belonging to a user.
     *
     * @param int $userId
     * @param string $notificationId
     * @return bool
     */
    public function deleteNotification(int $userId, string $notificationId): bool
    {
        $notification = Notification::where('user_id', $userId)
            ->where('id', $notificationId)
            ->firstOrFail();

        return $notification->delete();
    }
}
