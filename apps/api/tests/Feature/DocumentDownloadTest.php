<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $uploader;

    protected User $qc;

    protected User $sbap;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake default storage
        Storage::fake();

        // Create test users
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);
        $this->qc = User::factory()->create(['role' => 'qc']);
        $this->sbap = User::factory()->create(['role' => 'sbap']);
    }

    public function test_manager_can_download_any_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/test.pdf',
            'file_name' => 'test_document.pdf',
        ]);

        // Create fake file
        Storage::put($document->file_path, 'PDF content here');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertEquals('PDF content here', $response->streamedContent());
    }

    public function test_sbap_can_download_verified_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/verified.pdf',
            'file_name' => 'verified_doc.pdf',
        ]);

        Storage::put($document->file_path, 'Verified PDF content');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_sbap_cannot_download_pending_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
        ]);

        Storage::put($document->file_path, 'Pending PDF content');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_sbap_cannot_download_rejected_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/rejected.pdf',
        ]);

        Storage::put($document->file_path, 'Rejected PDF content');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_uploader_can_download_verified_document()
    {
        $this->actingAs($this->uploader);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/doc.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_uploader_can_download_own_unverified_document()
    {
        $this->actingAs($this->uploader);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
            'uploaded_by' => $this->uploader->id,
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_uploader_cannot_download_others_unverified_document()
    {
        $this->actingAs($this->uploader);

        $otherUploader = User::factory()->create(['role' => 'uploader']);
        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
            'uploaded_by' => $otherUploader->id,
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_qc_can_download_verified_document()
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/doc.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_qc_can_download_pending_document()
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_qc_cannot_download_rejected_document()
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/rejected.pdf',
        ]);

        Storage::put($document->file_path, 'Rejected PDF');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_download_returns_404_if_file_not_found()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/nonexistent.pdf',
        ]);

        // Don't create file in storage

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(404);
    }

    public function test_download_has_proper_headers()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/document.pdf',
            'file_name' => 'my_document.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=my_document.pdf');
    }

    public function test_manager_can_download_pending_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_manager_can_download_rejected_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/rejected.pdf',
        ]);

        Storage::put($document->file_path, 'Rejected PDF');

        $response = $this->get("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    /**
     * Inline view streams the same file as download, so it must follow
     * the same authorization matrix as the download endpoint.
     */
    public function test_view_requires_authentication()
    {
        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/verified.pdf',
        ]);

        Storage::put($document->file_path, 'Verified PDF content');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(401);
    }

    public function test_manager_can_view_any_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/test.pdf',
            'file_name' => 'test_document.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content here');

        $response = $this->get("/api/documents/{$document->id}/view");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertEquals('PDF content here', $response->streamedContent());
    }

    public function test_manager_can_view_pending_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(200);
    }

    public function test_manager_can_view_rejected_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/rejected.pdf',
        ]);

        Storage::put($document->file_path, 'Rejected PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(200);
    }

    public function test_sbap_can_view_verified_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/verified.pdf',
        ]);

        Storage::put($document->file_path, 'Verified PDF content');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(200);
    }

    public function test_sbap_cannot_view_pending_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(403);
    }

    public function test_sbap_cannot_view_rejected_document()
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/rejected.pdf',
        ]);

        Storage::put($document->file_path, 'Rejected PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(403);
    }

    public function test_uploader_can_view_own_unverified_document()
    {
        $this->actingAs($this->uploader);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
            'uploaded_by' => $this->uploader->id,
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(200);
    }

    public function test_uploader_cannot_view_others_unverified_document()
    {
        $this->actingAs($this->uploader);

        $otherUploader = User::factory()->create(['role' => 'uploader']);
        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
            'uploaded_by' => $otherUploader->id,
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(403);
    }

    public function test_qc_can_view_pending_document()
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->pending()->create([
            'file_path' => 'archives/pending.pdf',
        ]);

        Storage::put($document->file_path, 'Pending PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(200);
    }

    public function test_qc_cannot_view_rejected_document()
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->rejected()->create([
            'file_path' => 'archives/rejected.pdf',
        ]);

        Storage::put($document->file_path, 'Rejected PDF');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(403);
    }

    public function test_view_returns_404_for_soft_deleted_document()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/deleted.pdf',
        ]);

        Storage::put($document->file_path, 'Deleted PDF');
        $document->delete();

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(404);
    }

    public function test_view_returns_404_if_file_not_found()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/nonexistent.pdf',
        ]);

        // Don't create file in storage

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(404);
    }

    public function test_view_has_inline_disposition_header()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/document.pdf',
            'file_name' => 'my_document.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content');

        $response = $this->get("/api/documents/{$document->id}/view");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'inline',
            $response->baseResponse->headers->get('Content-Disposition')
        );
    }

    public function test_view_logs_audit_log()
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/audited.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content');

        $this->get("/api/documents/{$document->id}/view")
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'view_document',
            'model_id' => $document->id,
        ]);
    }
}
