<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_notifications(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Test Notification',
            'message' => 'Document approved',
            'type' => 'success',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Daftar notifikasi berhasil diambil.')
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_get_unread_notifications_count(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Test Unread',
            'message' => 'Message 1',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Jumlah notifikasi belum dibaca berhasil diambil.')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'To Read',
            'message' => 'Message 2',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Notifikasi berhasil ditandai sebagai sudah dibaca.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Unread 1',
            'message' => 'Msg 1',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Unread 2',
            'message' => 'Msg 2',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/mark-all-read');

        $response->assertStatus(200);
        $this->assertEquals(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_user_can_delete_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Delete Me',
            'message' => 'Msg Delete',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }
}
