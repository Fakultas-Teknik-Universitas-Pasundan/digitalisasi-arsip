<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $uploader;
    protected User $qc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);
        $this->qc = User::factory()->create(['role' => 'qc']);
    }

    public function test_manager_can_list_trashed_documents(): void
    {
        $this->actingAs($this->manager);

        $doc1 = Document::factory()->create();
        $doc2 = Document::factory()->create();

        $doc1->delete(); // Soft delete

        $response = $this->getJson('/api/documents/trashed');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Daftar dokumen terhapus berhasil diambil.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $doc1->id);
    }

    public function test_non_manager_cannot_list_trashed_documents(): void
    {
        $this->actingAs($this->uploader);

        $response = $this->getJson('/api/documents/trashed');

        $response->assertStatus(403);
    }

    public function test_manager_can_restore_soft_deleted_document(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->create([
            'file_name' => 'surat_penting.pdf',
        ]);
        $document->delete();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);

        $response = $this->postJson("/api/documents/{$document->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Dokumen berhasil dipulihkan.');

        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update_document',
            'user_id' => $this->manager->id,
        ]);
    }

    public function test_non_manager_cannot_restore_document(): void
    {
        $document = Document::factory()->create();
        $document->delete();

        $this->actingAs($this->uploader);

        $response = $this->postJson("/api/documents/{$document->id}/restore");

        $response->assertStatus(403);
    }

    public function test_manager_can_force_delete_document_and_remove_file(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->create([
            'file_path' => 'archives/permanently_deleted.pdf',
        ]);

        Storage::put($document->file_path, 'PDF Secret Data');
        Storage::assertExists($document->file_path);

        $document->delete(); // Soft delete first

        $response = $this->deleteJson("/api/documents/{$document->id}/force-delete");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Dokumen berhasil dihapus permanen.');

        // Record completely gone from database
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);

        // Physical file removed
        Storage::assertMissing($document->file_path);
    }

    public function test_non_manager_cannot_force_delete_document(): void
    {
        $document = Document::factory()->create();

        $this->actingAs($this->qc);

        $response = $this->deleteJson("/api/documents/{$document->id}/force-delete");

        $response->assertStatus(403);
    }
}
