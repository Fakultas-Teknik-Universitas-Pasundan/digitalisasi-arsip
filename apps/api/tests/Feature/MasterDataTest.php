<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DocumentType;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_active_prodis(): void
    {
        Prodi::create([
            'code' => 'IF',
            'name' => 'Teknik Informatika',
            'degree' => 'S1',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => UserRole::UPLOADER]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/prodis');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_can_create_new_prodi(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/prodis', [
                'code' => 'TKS',
                'name' => 'Teknik Kuantum',
                'degree' => 'S1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'TKS');

        $this->assertDatabaseHas('prodis', ['code' => 'TKS']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'create_prodi',
        ]);
    }

    public function test_authenticated_user_can_list_document_types(): void
    {
        DocumentType::create([
            'code' => 'nilai',
            'name' => 'Nilai Mata Kuliah',
            'requires_verification' => true,
        ]);

        $user = User::factory()->create(['role' => UserRole::UPLOADER]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/document-types');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }
}
