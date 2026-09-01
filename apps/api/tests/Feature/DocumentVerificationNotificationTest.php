<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_document_as_approved_creates_notification_for_uploader(): void
    {
        $uploader = User::factory()->create(['role' => UserRole::UPLOADER]);
        $qcStaff = User::factory()->create(['role' => UserRole::QC]);

        $document = Document::factory()->create([
            'uploaded_by' => $uploader->id,
            'status' => DocumentStatus::PENDING->value,
            'file_name' => 'KTI_2026.pdf',
        ]);

        $response = $this->actingAs($qcStaff, 'sanctum')
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::VERIFIED->value,
                'verification_note' => 'Dokumen sesuai standar',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $uploader->id,
            'type' => 'success',
        ]);

        $notification = Notification::where('user_id', $uploader->id)->first();
        $this->assertStringContainsString('KTI_2026.pdf', $notification->message);
        $this->assertStringContainsString('diverifikasi dan disetujui', $notification->message);
    }

    public function test_verifying_document_as_rejected_creates_notification_for_uploader(): void
    {
        $uploader = User::factory()->create(['role' => UserRole::UPLOADER]);
        $qcStaff = User::factory()->create(['role' => UserRole::QC]);

        $document = Document::factory()->create([
            'uploaded_by' => $uploader->id,
            'status' => DocumentStatus::PENDING->value,
            'file_name' => 'Ijazah_Budi.pdf',
        ]);

        $response = $this->actingAs($qcStaff, 'sanctum')
            ->patchJson("/api/documents/{$document->id}/verify", [
                'status' => DocumentStatus::REJECTED->value,
                'verification_note' => 'Stempel kurang jelas',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $uploader->id,
            'type' => 'danger',
        ]);

        $notification = Notification::where('user_id', $uploader->id)->first();
        $this->assertStringContainsString('Ijazah_Budi.pdf', $notification->message);
        $this->assertStringContainsString('Stempel kurang jelas', $notification->message);
    }
}
