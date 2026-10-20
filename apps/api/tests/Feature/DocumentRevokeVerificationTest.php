<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRevokeVerificationTest extends TestCase
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

        Storage::fake();

        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);
        $this->uploader2 = User::factory()->create(['role' => 'uploader']);
        $this->qc = User::factory()->create(['role' => 'qc']);
        $this->sbap = User::factory()->create(['role' => 'sbap']);
    }

    /**
     * Scenario 1: Manager successfully revokes verification.
     */
    public function test_manager_can_revoke_verification_of_verified_document(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'uploaded_by' => $this->uploader->id,
            'verified_by' => $this->qc->id,
            'verified_at' => now(),
            'verification_note' => 'Dokumen sesuai.',
            'file_path' => 'archives/revoked.pdf',
        ]);

        Storage::put($document->file_path, 'PDF content');

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification", [
            'reason' => 'QC salah verifikasi, perlu diperiksa ulang.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Verifikasi dokumen berhasil dibatalkan. Status dikembalikan ke menunggu verifikasi.')
            ->assertJsonPath('data.status', 'Menunggu Verifikasi');

        $document->refresh();

        // State transition: terverifikasi -> menunggu verifikasi
        $this->assertEquals('menunggu verifikasi', $document->status->value);

        // Verification metadata cleared
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);
        $this->assertNull($document->verification_note);

        // Document remains active (not soft deleted)
        $this->assertNull($document->deleted_at);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);

        // Physical file untouched
        Storage::assertExists($document->file_path);

        // Audit log recorded
        $auditLog = AuditLog::where('action', 'revoke_verification')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($this->manager->id, $auditLog->user_id);
        $this->assertEquals($document->id, $auditLog->metadata['document_id']);
        $this->assertEquals('terverifikasi', $auditLog->metadata['old_status']);
        $this->assertEquals('menunggu verifikasi', $auditLog->metadata['new_status']);
        $this->assertEquals('QC salah verifikasi, perlu diperiksa ulang.', $auditLog->metadata['reason']);
    }

    /**
     * Scenario 2: Uploader cannot revoke verification.
     */
    public function test_uploader_cannot_revoke_verification(): void
    {
        $this->actingAs($this->uploader);

        $document = Document::factory()->verified()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification");

        $response->assertStatus(403);

        $document->refresh();
        $this->assertEquals('terverifikasi', $document->status->value);
    }

    /**
     * Scenario 3: QC cannot revoke verification.
     */
    public function test_qc_cannot_revoke_verification(): void
    {
        $this->actingAs($this->qc);

        $document = Document::factory()->verified()->create();

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification");

        $response->assertStatus(403);

        $document->refresh();
        $this->assertEquals('terverifikasi', $document->status->value);
    }

    /**
     * Scenario 4: SBAP cannot revoke verification.
     */
    public function test_sbap_cannot_revoke_verification(): void
    {
        $this->actingAs($this->sbap);

        $document = Document::factory()->verified()->create();

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification");

        $response->assertStatus(403);

        $document->refresh();
        $this->assertEquals('terverifikasi', $document->status->value);
    }

    /**
     * Scenario 5: Manager cannot revoke a pending document.
     */
    public function test_manager_cannot_revoke_pending_document(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->pending()->create();

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Hanya dokumen dengan status terverifikasi yang dapat dibatalkan verifikasinya.')
            ->assertJsonValidationErrors(['document']);

        $document->refresh();
        $this->assertEquals('menunggu verifikasi', $document->status->value);
    }

    /**
     * Scenario 6: Manager cannot revoke a rejected document.
     */
    public function test_manager_cannot_revoke_rejected_document(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->rejected()->create();

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Hanya dokumen dengan status terverifikasi yang dapat dibatalkan verifikasinya.')
            ->assertJsonValidationErrors(['document']);

        $document->refresh();
        $this->assertEquals('tidak terverifikasi', $document->status->value);
    }

    /**
     * Reason longer than 500 characters is rejected with a validation error
     * on the reason field.
     */
    public function test_reason_over_500_characters_is_rejected(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create();

        $response = $this->postJson("/api/documents/{$document->id}/revoke-verification", [
            'reason' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $document->refresh();
        $this->assertEquals('terverifikasi', $document->status->value);
    }

    /**
     * Revoking a non-existent document returns 404 (implicit model binding).
     */
    public function test_manager_cannot_revoke_nonexistent_document(): void
    {
        $this->actingAs($this->manager);

        $this->postJson('/api/documents/999999/revoke-verification')
            ->assertStatus(404);
    }

    /**
     * Scenario 7: Document can still be revised after revocation
     * (existing update/revisi workflow works on the revoked document).
     */
    public function test_document_can_be_revised_after_revoke(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $revokeResponse = $this->postJson("/api/documents/{$document->id}/revoke-verification");
        $revokeResponse->assertStatus(200);

        // Uploader revises the revoked (now pending) document via existing workflow.
        // Manager performs the metadata update here (existing rule: pending docs
        // are not editable by uploader, but manager may update any document).
        $this->actingAs($this->manager);

        $updateResponse = $this->putJson("/api/documents/{$document->id}", [
            'prodi' => 'Teknologi Pangan',
        ]);

        $updateResponse->assertStatus(200);

        $document->refresh();
        $this->assertEquals('menunggu verifikasi', $document->status->value);
        $this->assertEquals('Teknologi Pangan', $document->prodi->value);
    }

    /**
     * Scenario 7b: Uploader revises after re-verification cycle — revoke then
     * verify again is possible since the document is pending after revoke.
     */
    public function test_document_can_be_verified_again_after_revoke(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $this->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(200);

        // Document is pending again, so QC can verify it again (previously blocked).
        $this->actingAs($this->qc);
        $this->patchJson("/api/documents/{$document->id}/verify", [
            'status' => 'terverifikasi',
        ])->assertStatus(200);

        $document->refresh();
        $this->assertEquals('terverifikasi', $document->status->value);
    }

    /**
     * Scenario 8: Audit log action uses REVOKE_VERIFICATION with old/new status.
     */
    public function test_revoke_produces_revoke_verification_audit_log_without_reason(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create();

        $this->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(200);

        $auditLog = AuditLog::where('action', 'revoke_verification')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($this->manager->id, $auditLog->user_id);
        $this->assertEquals('terverifikasi', $auditLog->metadata['old_status']);
        $this->assertEquals('menunggu verifikasi', $auditLog->metadata['new_status']);
        $this->assertArrayNotHasKey('reason', $auditLog->metadata);
    }

    /**
     * Regression: force-delete must still reject ACTIVE documents (F01 fix) —
     * onlyTrashed binding, active verified document cannot be force deleted.
     */
    public function test_force_delete_rejects_active_verified_document(): void
    {
        $this->actingAs($this->manager);

        $document = Document::factory()->verified()->create([
            'file_path' => 'archives/active.pdf',
        ]);
        Storage::put($document->file_path, 'PDF content');

        $response = $this->deleteJson("/api/documents/{$document->id}/force-delete");

        $response->assertStatus(404);

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        Storage::assertExists($document->file_path);
    }

    /**
     * Unauthenticated users cannot revoke.
     */
    public function test_unauthenticated_user_cannot_revoke(): void
    {
        $document = Document::factory()->verified()->create();

        $this->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(401);
    }
}
