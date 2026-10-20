<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Events\DocumentStatusChanged;
use App\Listeners\CreateNotificationOnDocumentStatusChanged;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Test tindaklanjut audit notifikasi Fase-1 (temuan Low F1 & P2).
 *
 * Mencakup:
 * - P1-a: lock perilaku kontrak saat ini — revoke verification TIDAK mengubah
 *   notifikasi lama (sesuai API_CONTRACT.md bagian revoke: tidak ada klausul notifikasi).
 * - P1-b: guard revoke pada dokumen non-verified (tidak menulis audit log).
 * - P2  : failure-injection — listener sinkron gagal di dalam transaksi
 *   verifyDocument() harus rollback penuh (status, metadata verifikasi,
 *   notifikasi, audit log) dan API merespons 500; verifikasi ulang tetap bisa.
 * - P3  : test defensif — PENDING tidak membuat notifikasi (jalur API dan
 *   listener-level), serta notifikasi strictly milik pemiliknya (404 cross-user).
 */
class DocumentNotificationEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $qc;
    protected User $uploader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->qc = User::factory()->create(['role' => 'qc']);
        $this->uploader = User::factory()->create(['role' => 'uploader']);

        // Registrasi listener AppServiceProvider (boot) bisa bertumpuk dengan
        // listener yang didaftarkan test — singkirkan lalu daftarkan ulang
        // listener asli secara eksplisit agar 1 event = tepat 1 notifikasi.
        Event::forget(DocumentStatusChanged::class);
        Event::listen(DocumentStatusChanged::class, CreateNotificationOnDocumentStatusChanged::class);
    }

    // ------------------------------------------------------------------
    // P1-a: Perilaku notifikasi lama saat revoke (perilaku kontrak saat ini)
    // ------------------------------------------------------------------

    public function test_revoke_verification_keeps_existing_notification_per_current_contract(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
            'file_name' => 'KTI_2026.pdf',
        ]);

        // QC memverifikasi -> listener membuat 1 notifikasi success untuk uploader.
        $this->actingAs($this->qc)
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::VERIFIED->value,
                'verification_note' => 'Dokumen sesuai standar.',
            ])
            ->assertStatus(200);

        $this->assertSame(1, Notification::where('user_id', $this->uploader->id)->count());

        // Manager mencabut verifikasi.
        $this->actingAs($this->manager)
            ->postJson("/api/documents/{$document->id}/revoke-verification", [
                'reason' => 'QC salah verifikasi.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'Menunggu Verifikasi');

        $document->refresh();
        $this->assertEquals(DocumentStatus::PENDING->value, $document->status->value);
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);
        $this->assertNull($document->verification_note);

        // Perilaku kontrak saat ini (API_CONTRACT.md bagian revoke-verification
        // tidak menyebut notifikasi): notifikasi lama TIDAK dihapus dan
        // TIDAK diubah (stale notification, temuan F1 — dibisakan via lock ini).
        $notification = Notification::where('user_id', $this->uploader->id)->firstOrFail();
        $this->assertSame('success', $notification->type);
        $this->assertStringContainsString('KTI_2026.pdf', $notification->message);
        $this->assertNull($notification->read_at);
    }

    public function test_revoke_verification_does_not_create_new_notification(): void
    {
        $document = Document::factory()->verified()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $this->assertSame(0, Notification::where('user_id', $this->uploader->id)->count());

        $this->actingAs($this->manager)
            ->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(200);

        // Revoke tidak memancarkan event apapun: tidak ada notifikasi baru.
        $this->assertSame(0, Notification::where('user_id', $this->uploader->id)->count());
    }

    public function test_reverify_after_revoke_creates_second_notification(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $this->actingAs($this->qc)
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::VERIFIED->value,
            ])->assertStatus(200);

        $this->actingAs($this->manager)
            ->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(200);

        // Verifikasi ulang setelah revoke: notifikasi kedua dibuat (riwayat
        // "verified -> (notif lama tetap) -> verified" tetap terlihat uploader).
        $this->actingAs($this->qc)
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::VERIFIED->value,
            ])->assertStatus(200);

        $this->assertSame(2, Notification::where('user_id', $this->uploader->id)->count());
        $this->assertSame(
            2,
            Notification::where('user_id', $this->uploader->id)->where('type', 'success')->count()
        );
    }

    // ------------------------------------------------------------------
    // P1-b: Guard revoke pada dokumen non-verified tidak menyentuh apa pun
    // ------------------------------------------------------------------

    public function test_revoke_pending_document_is_rejected_with_422(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Hanya dokumen dengan status terverifikasi yang dapat dibatalkan verifikasinya.'
            )
            ->assertJsonValidationErrors(['document']);

        $document->refresh();
        $this->assertEquals(DocumentStatus::PENDING->value, $document->status->value);
        $this->assertNull($document->deleted_at);
    }

    public function test_revoke_pending_document_does_not_write_audit_log(): void
    {
        $document = Document::factory()->pending()->create();

        $this->actingAs($this->manager)
            ->postJson("/api/documents/{$document->id}/revoke-verification")
            ->assertStatus(422);

        $this->assertSame(
            0,
            AuditLog::where('action', 'revoke_verification')
                ->where('model_id', $document->id)
                ->count()
        );
    }

    // ------------------------------------------------------------------
    // P2: Listener gagal di dalam transaksi verifyDocument() -> rollback penuh
    // ------------------------------------------------------------------

    public function test_listener_failure_rolls_back_document_verification_transaction(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        // Ganti listener terdaftar dengan satu closure yang selalu throw —
        // mensimulasikan kegagalan listener DI DALAM transaksi
        // (event() dipanggil sebelum DB::commit() di verifyDocument()).
        Event::forget(DocumentStatusChanged::class);
        Event::listen(DocumentStatusChanged::class, function (): never {
            throw new \RuntimeException('Simulasi kegagalan listener notifikasi.');
        });

        $response = $this->actingAs($this->qc)
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::VERIFIED->value,
                'verification_note' => 'Seharusnya rollback.',
            ]);

        // Controller hanya menangkap ValidationException; RuntimeException
        // menyebar ke handler Laravel -> HTTP 500 (exception handling default).
        $response->assertStatus(500);

        // Transaksi rollback PENUH:
        // 1. Dokumen kembali PENDING, seluruh metadata verifikasi kosong.
        $document->refresh();
        $this->assertEquals(DocumentStatus::PENDING->value, $document->status->value);
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);
        $this->assertNull($document->verification_note);

        // 2. Notifikasi tidak terlanjur tersimpan (create di listener ikut rollback).
        $this->assertSame(0, Notification::where('user_id', $this->uploader->id)->count());

        // 3. Audit log verify/reject untuk dokumen ini tidak terlanjur tersimpan
        //    (AuditLog::log menulis via koneksi yang sama di dalam transaksi).
        $this->assertSame(
            0,
            AuditLog::where('model_id', $document->id)
                ->whereIn('action', ['verify_document', 'reject_document'])
                ->count()
        );

        // 4. Dokumen masih ada dan bisa diverifikasi ulang (QC bisa mencoba lagi).
        $this->assertDatabaseHas('documents', ['id' => $document->id]);

        // Pulihkan listener asli, lalu verifikasi ulang harus berhasil (200).
        Event::forget(DocumentStatusChanged::class);
        Event::listen(DocumentStatusChanged::class, CreateNotificationOnDocumentStatusChanged::class);

        $this->actingAs($this->qc)
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::VERIFIED->value,
            ])->assertStatus(200);

        $document->refresh();
        $this->assertEquals(DocumentStatus::VERIFIED->value, $document->status->value);
        $this->assertSame(1, Notification::where('user_id', $this->uploader->id)->count());
    }

    // ------------------------------------------------------------------
    // P3-a: PENDING tidak membuat notifikasi (jalur API)
    // ------------------------------------------------------------------

    public function test_verify_with_pending_status_is_rejected_and_creates_no_notification(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        $response = $this->actingAs($this->qc)
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::PENDING->value,
            ]);

        // VerifyDocumentRequest: Rule::in([VERIFIED, REJECTED]) -> 422.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $document->refresh();
        $this->assertEquals(DocumentStatus::PENDING->value, $document->status->value);
        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_at);

        // Tidak ada notifikasi yang dibuat.
        $this->assertSame(0, Notification::where('user_id', $this->uploader->id)->count());
    }

    // ------------------------------------------------------------------
    // P3-b: Listener-level — status selain VERIFIED/REJECTED tidak membuat notifikasi
    // ------------------------------------------------------------------

    public function test_listener_ignores_statuses_other_than_verified_and_rejected(): void
    {
        $uploader = User::factory()->create(['role' => 'uploader']);

        foreach ([DocumentStatus::PENDING->value, 'status-tidak-dikenal', ''] as $status) {
            $document = Document::factory()->pending()->create([
                'uploaded_by' => $uploader->id,
            ]);

            event(new DocumentStatusChanged($document, $status, null));
        }

        $this->assertSame(
            0,
            Notification::where('user_id', $uploader->id)->count(),
            'Listener seharusnya tidak membuat notifikasi untuk status selain VERIFIED/REJECTED.'
        );
    }

    public function test_listener_creates_notification_when_status_is_verified(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        event(new DocumentStatusChanged($document, DocumentStatus::VERIFIED->value, null));

        $this->assertSame(1, Notification::where('user_id', $this->uploader->id)->count());
    }

    public function test_listener_creates_notification_when_status_is_rejected_with_reason(): void
    {
        $document = Document::factory()->pending()->create([
            'uploaded_by' => $this->uploader->id,
        ]);

        event(new DocumentStatusChanged($document, DocumentStatus::REJECTED->value, 'Stempel kurang jelas.'));

        $notification = Notification::where('user_id', $this->uploader->id)->firstOrFail();
        $this->assertSame('danger', $notification->type);
        $this->assertStringContainsString('Stempel kurang jelas.', $notification->message);
    }

    // ------------------------------------------------------------------
    // P3-c: Notifikasi strictly milik pemiliknya — cross-user 404
    // ------------------------------------------------------------------

    public function test_other_user_cannot_mark_or_delete_notification_of_owner(): void
    {
        $owner = User::factory()->create(['role' => 'uploader']);
        $intruder = User::factory()->create(['role' => 'uploader']);

        $notification = Notification::create([
            'user_id' => $owner->id,
            'title' => 'Judul',
            'message' => 'Pesan milik owner.',
            'type' => 'success',
        ]);

        // PATCH read milik orang lain -> 404 (firstOrFail dengan scoping user_id).
        $this->actingAs($intruder)
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);

        // DELETE milik orang lain -> 404.
        $this->actingAs($intruder)
            ->deleteJson("/api/notifications/{$notification->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);

        // Owner tetap bisa akses sendiri (sanity check).
        $this->actingAs($owner)
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(200);

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
