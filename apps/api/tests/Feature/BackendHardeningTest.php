<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Prodi;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackendHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploader_only_sees_their_own_documents_in_list(): void
    {
        $uploaderA = User::factory()->create(['role' => UserRole::UPLOADER]);
        $uploaderB = User::factory()->create(['role' => UserRole::UPLOADER]);

        Document::factory()->create([
            'uploaded_by' => $uploaderA->id,
            'file_name' => 'Doc_Uploader_A.pdf',
        ]);

        Document::factory()->create([
            'uploaded_by' => $uploaderB->id,
            'file_name' => 'Doc_Uploader_B.pdf',
        ]);

        $response = $this->actingAs($uploaderA, 'sanctum')
            ->getJson('/api/documents');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.file_name', 'Doc_Uploader_A.pdf');
    }

    public function test_manager_sees_all_documents_in_list(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $uploaderA = User::factory()->create(['role' => UserRole::UPLOADER]);
        $uploaderB = User::factory()->create(['role' => UserRole::UPLOADER]);

        Document::factory()->create(['uploaded_by' => $uploaderA->id]);
        Document::factory()->create(['uploaded_by' => $uploaderB->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/documents');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_per_page_is_capped_at_100(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/documents?per_page=99999');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_like_wildcard_character_does_not_match_all_records(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        Document::factory()->create([
            'file_name' => 'NormalFile.pdf',
            'npm' => '193040001',
        ]);

        // Searching for '%' alone should not return normal documents that don't have '%' in their name
        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/documents?search=%25');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_user_password_is_automatically_hashed_via_cast(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testcast@unpas.ac.id',
            'password' => 'plaintextpassword',
            'role' => UserRole::UPLOADER,
        ]);

        $this->assertTrue(Hash::check('plaintextpassword', $user->password));
    }
}
