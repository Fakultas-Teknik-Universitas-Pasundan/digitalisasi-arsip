<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_system_settings(): void
    {
        SystemSetting::create([
            'key' => 'max_upload_size_mb',
            'value' => '10',
            'group_name' => 'storage',
        ]);

        $user = User::factory()->create(['role' => UserRole::UPLOADER]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/system-settings');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.max_upload_size_mb', '10');
    }

    public function test_manager_can_update_system_settings(): void
    {
        SystemSetting::create([
            'key' => 'max_upload_size_mb',
            'value' => '10',
            'group_name' => 'storage',
        ]);

        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager, 'sanctum')
            ->putJson('/api/system-settings', [
                'settings' => [
                    [
                        'key' => 'max_upload_size_mb',
                        'value' => '25',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'max_upload_size_mb',
            'value' => '25',
            'updated_by' => $manager->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'update_settings',
        ]);
    }

    public function test_non_manager_cannot_update_system_settings(): void
    {
        SystemSetting::create([
            'key' => 'max_upload_size_mb',
            'value' => '10',
        ]);

        $uploader = User::factory()->create(['role' => UserRole::UPLOADER]);

        $response = $this->actingAs($uploader, 'sanctum')
            ->putJson('/api/system-settings', [
                'settings' => [
                    [
                        'key' => 'max_upload_size_mb',
                        'value' => '50',
                    ],
                ],
            ]);

        $response->assertStatus(403);
    }
}
