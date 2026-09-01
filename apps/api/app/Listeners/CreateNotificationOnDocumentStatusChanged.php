<?php

namespace App\Listeners;

use App\Enums\DocumentStatus;
use App\Events\DocumentStatusChanged;
use App\Models\Notification;

class CreateNotificationOnDocumentStatusChanged
{
    /**
     * Handle the event.
     */
    public function handle(DocumentStatusChanged $event): void
    {
        $document = $event->document;
        $uploaderId = $document->uploaded_by;

        if (!$uploaderId) {
            return;
        }

        $statusValue = is_object($event->status) ? $event->status->value : (string) $event->status;

        if ($statusValue === DocumentStatus::VERIFIED->value || $statusValue === 'terverifikasi' || $statusValue === 'VERIFIED') {
            Notification::create([
                'user_id' => $uploaderId,
                'title' => 'Dokumen Diverifikasi',
                'message' => "Dokumen {$document->file_name} telah diverifikasi dan disetujui.",
                'type' => 'success',
                'action_url' => "/documents/{$document->id}",
            ]);
        } elseif ($statusValue === DocumentStatus::REJECTED->value || $statusValue === 'tidak_terverifikasi' || $statusValue === 'REJECTED') {
            $reason = $event->note ? " Alasan: {$event->note}." : '';
            Notification::create([
                'user_id' => $uploaderId,
                'title' => 'Dokumen Ditolak',
                'message' => "Dokumen {$document->file_name} ditolak.{$reason}",
                'type' => 'danger',
                'action_url' => "/documents/{$document->id}",
            ]);
        }
    }
}
