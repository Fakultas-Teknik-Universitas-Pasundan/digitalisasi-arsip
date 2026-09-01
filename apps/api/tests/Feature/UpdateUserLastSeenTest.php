<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserLastSeenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_api_request_updates_user_last_seen_at(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MANAGER,
            'last_seen_at' => null,
        ]);

        $this->assertNull($user->last_seen_at);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->last_seen_at);
    }
}
