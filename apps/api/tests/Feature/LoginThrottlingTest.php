<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_records_attempt_and_returns_remaining_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Email atau password salah.')
            ->assertJsonStructure(['remaining_attempts']);

        // Verify login attempt was recorded in database
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_account_locks_after_maximum_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // Trigger 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'locked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be blocked with 423 (Locked) or 429 (Too Many Requests)
        $response = $this->postJson('/api/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'secret123',
        ]);

        $this->assertTrue(in_array($response->status(), [423, 429]));
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'success@example.com',
            'password' => bcrypt('valid-password'),
        ]);

        // 2 failed attempts
        $this->postJson('/api/auth/login', [
            'email' => 'success@example.com',
            'password' => 'wrong-password',
        ]);
        $this->postJson('/api/auth/login', [
            'email' => 'success@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertEquals(2, DB::table('login_attempts')->where('email', 'success@example.com')->count());

        // Successful login
        $response = $this->postJson('/api/auth/login', [
            'email' => 'success@example.com',
            'password' => 'valid-password',
        ]);

        $response->assertStatus(200);

        // Attempts should be cleared
        $this->assertEquals(0, DB::table('login_attempts')->where('email', 'success@example.com')->count());
    }
}
