<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_user_statistics()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        // Create 5 users (including manager)
        User::factory()->count(4)->create();

        // Create audit logs for active users check
        // Manager + 1 other user active
        AuditLog::factory()->create([
            'user_id' => $manager->id,
            'created_at' => now()
        ]);

        $otherUser = User::first();
        if($otherUser->id !== $manager->id) {
             AuditLog::factory()->create([
                'user_id' => $otherUser->id,
                'created_at' => now()->subDays(5)
            ]);
        }

        $response = $this->actingAs($manager)
            ->getJson('/api/users/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_users',
                    'active_users',
                    'new_users'
                ]
            ]);
    }

    public function test_manager_can_view_document_statistics()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        // Create documents with different statuses
        Document::factory()->count(3)->create(['status' => DocumentStatus::VERIFIED]);
        Document::factory()->count(2)->create(['status' => DocumentStatus::PENDING]);
        Document::factory()->count(1)->create(['status' => DocumentStatus::REJECTED]);

        $response = $this->actingAs($manager)
            ->getJson('/api/documents/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_documents',
                    'verified_documents',
                    'pending_documents',
                    'rejected_documents'
                ]
            ])
            ->assertJsonPath('data.total_documents', 6)
            ->assertJsonPath('data.verified_documents', 3)
            ->assertJsonPath('data.pending_documents', 2)
            ->assertJsonPath('data.rejected_documents', 1);
    }

    /**
     * Regression (Fase-1): QC must keep access to document statistics.
     */
    public function test_qc_can_view_document_statistics()
    {
        $qc = User::factory()->create(['role' => UserRole::QC]);

        Document::factory()->count(3)->create(['status' => DocumentStatus::VERIFIED]);
        Document::factory()->count(2)->create(['status' => DocumentStatus::PENDING]);
        Document::factory()->count(1)->create(['status' => DocumentStatus::REJECTED]);

        $response = $this->actingAs($qc)
            ->getJson('/api/documents/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_documents',
                    'verified_documents',
                    'pending_documents',
                    'rejected_documents'
                ]
            ])
            ->assertJsonPath('data.total_documents', 6);
    }

    /**
     * Regression (Fase-1): Uploader must keep pre-Fase-1 access to
     * document statistics, with a fully valid response body.
     */
    public function test_uploader_can_view_document_statistics()
    {
        $uploader = User::factory()->create(['role' => UserRole::UPLOADER]);

        Document::factory()->count(3)->create(['status' => DocumentStatus::VERIFIED]);
        Document::factory()->count(2)->create(['status' => DocumentStatus::PENDING]);
        Document::factory()->count(1)->create(['status' => DocumentStatus::REJECTED]);

        $response = $this->actingAs($uploader)
            ->getJson('/api/documents/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_documents',
                    'verified_documents',
                    'pending_documents',
                    'rejected_documents'
                ]
            ])
            ->assertJsonPath('data.total_documents', 6)
            ->assertJsonPath('data.verified_documents', 3)
            ->assertJsonPath('data.pending_documents', 2)
            ->assertJsonPath('data.rejected_documents', 1);
    }

    /**
     * Regression (Fase-1): SBAP must stay blocked from document statistics.
     */
    public function test_sbap_cannot_view_document_statistics()
    {
        $sbap = User::factory()->create(['role' => UserRole::SBAP]);

        $response = $this->actingAs($sbap)
            ->getJson('/api/documents/statistics');

        $response->assertStatus(403);
    }
}
