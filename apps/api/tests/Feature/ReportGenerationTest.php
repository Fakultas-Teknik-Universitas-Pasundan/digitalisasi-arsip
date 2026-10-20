<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles or specific data if needed, but factories should handle most
    }

    public function test_manager_can_generate_pdf_report()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        // Create some dummy data
        Document::factory()->count(5)->create();
        AuditLog::factory()->count(10)->create();

        $response = $this->actingAs($manager)
            ->postJson('/api/reports/generate', [
                'period_start' => now()->subMonth()->toDateString(),
                'period_end' => now()->toDateString(),
                'format' => 'pdf',
                'type' => 'monthly',
                'content' => ['upload_stats', 'doc_status'],
            ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_can_generate_excel_report()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        // Create some dummy data
        Document::factory()->count(5)->create();
        AuditLog::factory()->count(10)->create();

        $response = $this->actingAs($manager)
            ->postJson('/api/reports/generate', [
                'period_start' => now()->subMonth()->toDateString(),
                'period_end' => now()->toDateString(),
                'format' => 'xlsx',
                'type' => 'monthly',
                'content' => ['upload_stats', 'doc_status'],
            ]);

        // XLSX must be a real, downloadable spreadsheet file.
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // BinaryFileResponse::getContent() always returns false, so read the
        // underlying file bytes directly to inspect the real payload.
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);
        $content = file_get_contents($base->getFile()->getPathname());
        $this->assertNotFalse($content);
        $this->assertGreaterThan(0, strlen($content));
        // XLSX is a ZIP container: it must start with the PK\x03\x04 magic bytes.
        $this->assertSame("PK\x03\x04", substr($content, 0, 4));
        $this->assertStringContainsString('laporan_monthly_', (string) $response->headers->get('content-disposition'));
    }

    public function test_manager_can_generate_csv_report()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        Document::factory()->count(5)->create();
        AuditLog::factory()->count(10)->create();

        $response = $this->actingAs($manager)
            ->postJson('/api/reports/generate', [
                'period_start' => now()->subMonth()->toDateString(),
                'period_end' => now()->toDateString(),
                'format' => 'csv',
                'type' => 'monthly',
                'content' => ['upload_stats', 'doc_status'],
            ]);

        // CSV must be a real UTF-8 (BOM) file with section headers and data rows.
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertNotFalse($content);
        // BOM UTF-8 must sit exactly at byte 0 so Excel reads it correctly.
        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));
        $this->assertStringContainsString('Ringkasan Eksekutif', $content);
        $this->assertStringContainsString('Total Dokumen', $content);
        $this->assertStringContainsString('Statistik Unggahan', $content);
        $this->assertStringContainsString('laporan_monthly_', (string) $response->headers->get('content-disposition'));
    }

    public function test_staff_cannot_generate_report()
    {
        $uploader = User::factory()->create(['role' => UserRole::UPLOADER]);

        $response = $this->actingAs($uploader)
            ->postJson('/api/reports/generate', [
                'period_start' => now()->subMonth()->toDateString(),
                'period_end' => now()->toDateString(),
                'format' => 'pdf',
                'type' => 'monthly',
            ]);

        $response->assertStatus(403);
    }

    public function test_validation_errors()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager)
            ->postJson('/api/reports/generate', [
                // Missing required fields
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period_start', 'period_end', 'format', 'type']);
    }

    public function test_dashboard_stats()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager)
            ->getJson('/api/reports/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_documents',
                    'verified_documents',
                    'pending_documents',
                    'rejected_documents',
                ],
                'period' => [
                    'start_date',
                    'end_date',
                ],
            ]);
    }
}
