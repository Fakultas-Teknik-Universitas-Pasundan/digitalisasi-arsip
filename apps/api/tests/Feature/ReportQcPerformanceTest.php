<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportQcPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_get_qc_performance_report(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $qcStaff = User::factory()->create([
            'role' => UserRole::QC,
            'name' => 'Budi Verifikator',
            'email' => 'budi.qc@unpas.ac.id',
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/reports/qc-performance');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'staff',
                        'email',
                        'role',
                        'terverifikasi',
                        'ditolak',
                        'accuracy_rate',
                        'is_online',
                        'last_seen_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_document_statistics_includes_by_document_type_breakdown(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/documents/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_documents',
                    'verified_documents',
                    'pending_documents',
                    'rejected_documents',
                    'by_document_type' => [
                        'nilai',
                        'transkrip',
                        'ijazah',
                        'berita_acara_sidang',
                    ],
                ],
            ]);
    }
}
