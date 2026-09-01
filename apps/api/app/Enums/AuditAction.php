<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATE_USER = 'create_user';
    case UPDATE_USER = 'update_user';
    case DELETE_USER = 'delete_user';
    case UPLOAD_DOCUMENT = 'upload_document';
    case VERIFY_DOCUMENT = 'verify_document';
    case REJECT_DOCUMENT = 'reject_document';
    case UPDATE_DOCUMENT = 'update_document';
    case DELETE_DOCUMENT = 'delete_document';
    case DOWNLOAD_DOCUMENT = 'download_document';
    case VIEW_DOCUMENT = 'view_document';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case UPDATE_SETTINGS = 'update_settings';
    case CREATE_PRODI = 'create_prodi';

    /**
     * Get the label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATE_USER => 'Buat Pengguna',
            self::UPDATE_USER => 'Update Pengguna',
            self::DELETE_USER => 'Hapus Pengguna',
            self::UPLOAD_DOCUMENT => 'Unggah',
            self::VERIFY_DOCUMENT => 'Verifikasi',
            self::REJECT_DOCUMENT => 'Tolak',
            self::UPDATE_DOCUMENT => 'Update Dokumen',
            self::DELETE_DOCUMENT => 'Hapus Dokumen',
            self::DOWNLOAD_DOCUMENT => 'Unduh',
            self::VIEW_DOCUMENT => 'Lihat',
            self::LOGIN => 'Masuk',
            self::LOGOUT => 'Keluar',
            self::UPDATE_SETTINGS => 'Update Pengaturan',
            self::CREATE_PRODI => 'Tambah Prodi',
        };
    }

    /**
     * Get the color for the action (optional for UI).
     */
    public function color(): string
    {
        return match ($this) {
            self::VERIFY_DOCUMENT, self::CREATE_USER, self::LOGIN => 'success',
            self::REJECT_DOCUMENT, self::DELETE_USER, self::DELETE_DOCUMENT => 'danger',
            self::UPLOAD_DOCUMENT, self::UPDATE_USER, self::UPDATE_DOCUMENT, self::UPDATE_SETTINGS => 'primary',
            self::CREATE_PRODI => 'info',
            default => 'secondary',
        };
    }
}
