<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $uploader;
    protected User $uploader2;
    protected User $qc;
    protected User $sbap;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake storage
        Storage::fake('local');

        // Create test users
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);
        $this->uploader2 = User::factory()->create(['role' => 'uploader']);
        $this->qc = User::factory()->create(['role' => 'qc']);
        $this->sbap = User::factory()->create(['role' => 'sbap']);
    }

    public function test_manager_can_delete_rejected_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/test.pdf',
        ]);

        // Create fake file
        Storage::put($document->file_path, 'fake file content');

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Dokumen berhasil dihapus.',
            ]);

        // Verify document soft deleted from database
        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);

        // Verify file is preserved in storage during soft delete
        Storage::assertExists($document->file_path);
    }

    public function test_uploader_can_delete_own_rejected_document()
    {
        $this->actingAs($this->uploader);

        $document = Document::factory()->rejected()->create([
            'uploaded_by' => $this->uploader->id,
            'file_path' => 'archives/test.pdf',
        ]);

        Storage::put($document->file_path, 'fake file content');

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);

        // Verify file is preserved in storage during soft delete
        Storage::assertExists($document->file_path);
    }

    public function test_uploader_can_reupload_document_after_deleting_rejected_document()
    {
        $this->actingAs($this->uploader);

        $data = [
            'document_type' => 'nilai',
            'prodi' => 'Teknik Informatika',
            'tahun_ajaran' => '2025/2026',
            'mata_kuliah' => 'Struktur Data',
            'kelas' => 'A',
        ];

        // 1. Create a rejected document with these details
        $doc = Document::factory()->rejected()->create(array_merge($data, [
            'uploaded_by' => $this->uploader->id,
            'duplicate_key' => Document::generateDuplicateKey($data),
        ]));

        // 2. Delete the rejected document
        $deleteResponse = $this->deleteJson("/api/documents/{$doc->id}");
        $deleteResponse->assertStatus(200);

        // 3. Re-upload a new file with the exact same metadata
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('nilai_fixed.pdf', '%PDF-1.4');
        $uploadResponse = $this->postJson('/api/documents', array_merge($data, [
            'file' => $file,
        ]));

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('data.mata_kuliah', 'Struktur Data');
    }

    public function test_uploader_cannot_delete_others_rejected_document()
    {
        $this->actingAs($this->uploader);

        $document = Document::factory()->rejected()->create([
            'uploaded_by' => $this->uploader2->id,
        ]);

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);

        // Document should still exist
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_qc_cannot_delete_document()
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->rejected()->create();

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_sbap_cannot_delete_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->rejected()->create();

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_cannot_delete_pending_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->pending()->create();

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_cannot_delete_verified_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create();

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_audit_log_recorded_for_delete()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/test.pdf',
            'file_name' => 'test.pdf',
        ]);

        Storage::put($document->file_path, 'fake file content');

        $this->deleteJson("/api/documents/{$document->id}");

        // Verify audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->manager->id,
            'action' => 'delete_document',
        ]);

        $auditLog = AuditLog::where('action', 'delete_document')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($document->id, $auditLog->metadata['document_id']);
        $this->assertEquals('test.pdf', $auditLog->metadata['file_name']);
    }

    public function test_soft_delete_preserves_physical_file_and_force_delete_removes_it()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/nilai/2026/01/test.pdf',
        ]);

        // Create fake file
        Storage::put($document->file_path, 'PDF file content here');
        Storage::assertExists($document->file_path);

        $this->deleteJson("/api/documents/{$document->id}");

        // File should still be preserved after soft delete
        Storage::assertExists($document->file_path);

        // Force delete via service removes physical file
        app(\App\Services\DocumentService::class)->forceDeleteDocument($document);
        Storage::assertMissing($document->file_path);
    }

    public function test_unauthenticated_user_cannot_delete()
    {
        $document = Document::factory()->rejected()->create();

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(401);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }
}
