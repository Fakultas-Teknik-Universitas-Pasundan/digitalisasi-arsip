<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileEncryptionService
{
    /**
     * Encrypt and store a file.
     *
     * @param string $content File content to encrypt
     * @param string $path Storage path
     * @return bool
     */
    public function encryptAndStore(string $content, string $path): bool
    {
        try {
            $encryptedContent = Crypt::encrypt($content);
            return Storage::put($path, $encryptedContent);
        } catch (\Exception $e) {
            Log::error('File encryption failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve and decrypt a file.
     *
     * @param string $path Storage path
     * @return string|null Decrypted content or null on failure
     */
    public function retrieveAndDecrypt(string $path): ?string
    {
        try {
            if (!Storage::exists($path)) {
                return null;
            }

            $encryptedContent = Storage::get($path);
            return Crypt::decrypt($encryptedContent);
        } catch (\Exception $e) {
            Log::error('File decryption failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if the binary content has a valid PDF magic signature (%PDF-).
     *
     * @param string $content
     * @return bool
     */
    public function hasValidPdfSignature(string $content): bool
    {
        return str_starts_with($content, '%PDF-');
    }

    /**
     * Check if file encryption is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('filesystems.encrypt_documents', false);
    }
}
