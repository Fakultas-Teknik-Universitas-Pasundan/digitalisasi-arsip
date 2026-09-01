<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\AuditAction;
use App\Enums\ModelType;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @method static string escapeLike(string $value)
 */

class DocumentService
{
    /**
     * Upload a new document with duplicate check.
     *
     * @param array $data
     * @param UploadedFile $file
     * @param int $userId
     * @return Document
     * @throws ConflictHttpException
     */
    public function uploadDocument(array $data, UploadedFile $file, int $userId): Document
    {
        // Generate duplicate key
        $duplicateKey = Document::generateDuplicateKey($data);

        DB::beginTransaction();

        try {
            // Store file in private storage
            $filePath = $this->storeFile($file, $data['document_type']);

            // Create document record
            $document = Document::create([
                'document_type' => $data['document_type'],
                'duplicate_key' => $duplicateKey,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'prodi' => $data['prodi'],
                'tahun_ajaran' => $data['tahun_ajaran'] ?? null,
                'mata_kuliah' => $data['mata_kuliah'] ?? null,
                'kelas' => $data['kelas'] ?? null,
                'tahun_lulus' => $data['tahun_lulus'] ?? null,
                'npm' => $data['npm'] ?? null,
                'status' => DocumentStatus::PENDING->value,
                'uploaded_by' => $userId,
            ]);

            // Log upload activity
            $this->logUpload($document, $userId);

            DB::commit();

            return $document->fresh(['uploader']);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // Delete file if database transaction fails
            if (isset($filePath)) {
                Storage::delete($filePath);
            }

            // Check if it's a duplicate key violation (MySQL error code 23000)
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'duplicate_key')) {
                throw new ConflictHttpException(
                    $this->getDuplicateMessage($data['document_type'])
                );
            }

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete file if database transaction fails
            if (isset($filePath)) {
                Storage::delete($filePath);
            }

            throw $e;
        }
    }

    /**
     * Get duplicate message based on document type.
     *
     * @param string $documentType
     * @return string
     */
    protected function getDuplicateMessage(string $documentType): string
    {
        if ($documentType === 'nilai') {
            return 'Dokumen nilai dengan kombinasi tahun ajaran, prodi, mata kuliah, dan kelas yang sama sudah ada.';
        }

        return "Dokumen {$documentType} dengan kombinasi prodi, tahun lulus, dan NPM yang sama sudah ada.";
    }

    /**
     * Store uploaded file in private storage.
     *
     * @param UploadedFile $file
     * @param string $documentType
     * @return string File path
     */
    protected function storeFile(UploadedFile $file, string $documentType): string
    {
        // Store in archives/{document_type}/YYYY/MM/ directory structure
        $directory = sprintf(
            'archives/%s/%s/%s',
            $documentType,
            now()->format('Y'),
            now()->format('m')
        );

        // Use Storage::putFile to auto-generate unique filename
        return Storage::putFile($directory, $file);
    }

    /**
     * Log document upload activity.
     *
     * @param Document $document
     * @param int $userId
     * @return void
     */
    protected function logUpload(Document $document, int $userId): void
    {
        AuditLog::log(
            action: AuditAction::UPLOAD_DOCUMENT->value,
            description: "Dokumen {$document->document_type->value} '{$document->file_name}' diunggah untuk prodi {$document->prodi->value}.",
            metadata: [
                'document_id' => $document->id,
                'document_type' => $document->document_type->value,
                'file_name' => $document->file_name,
                'file_size' => $document->file_size,
                'prodi' => $document->prodi->value,
            ],
            modelType: ModelType::DOCUMENT->value,
            modelId: $document->id,
            userId: $userId
        );
    }

    /**
     * Soft delete a document (preserves physical file for potential restore).
     *
     * @param Document $document
     * @return bool
     */
    public function deleteDocument(Document $document): bool
    {
        DB::beginTransaction();

        try {
            $documentId = $document->id;
            $fileName = $document->file_name;

            // Release duplicate_key so new document with same metadata can be uploaded
            $document->update([
                'duplicate_key' => $document->duplicate_key . '_del_' . now()->timestamp . '_' . Str::random(6),
            ]);

            // Soft delete document record (physical file is preserved)
            $deleted = $document->delete();

            if ($deleted) {
                // Log deletion
                AuditLog::log(
                    action: AuditAction::DELETE_DOCUMENT->value,
                    description: "Dokumen '{$fileName}' dihapus (soft delete).",
                    metadata: [
                        'document_id' => $documentId,
                        'file_name' => $fileName,
                    ]
                );
            }

            DB::commit();

            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Permanently delete a document and its physical file.
     * Use this for cleanup of soft-deleted documents.
     *
     * @param Document $document
     * @return bool
     */
    public function forceDeleteDocument(Document $document): bool
    {
        DB::beginTransaction();

        try {
            $documentId = $document->id;
            $fileName = $document->file_name;
            $filePath = $document->file_path;

            // Permanently delete document record
            $deleted = $document->forceDelete();

            if ($deleted) {
                // Delete physical file from storage
                Storage::delete($filePath);

                // Log permanent deletion
                AuditLog::log(
                    action: AuditAction::DELETE_DOCUMENT->value,
                    description: "Dokumen '{$fileName}' dihapus permanen beserta file fisik.",
                    metadata: [
                        'document_id' => $documentId,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                    ]
                );
            }

            DB::commit();

            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Verify or reject a document.
     *
     * @param Document $document
     * @param string $status
     * @param string|null $note
     * @param int $verifierId
     * @return Document
     * @throws \Illuminate\Validation\ValidationException
     */
    public function verifyDocument(
        Document $document,
        string $status,
        ?string $note,
        int $verifierId
    ): Document {
        // Validate document is still pending
        if ($document->status !== DocumentStatus::PENDING) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document' => ['Dokumen sudah diverifikasi sebelumnya dan tidak dapat diverifikasi ulang.'],
            ]);
        }

        DB::beginTransaction();

        try {
            // Update document
            $document->update([
                'status' => $status,
                'verification_note' => $note,
                'verified_by' => $verifierId,
                'verified_at' => now(),
            ]);

            // Log verification activity
            $this->logVerification($document, $status, $verifierId);

            // Dispatch event for notification creation
            event(new \App\Events\DocumentStatusChanged($document, $status, $note));

            DB::commit();

            return $document->fresh(['uploader', 'verifier']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Log document verification activity.
     *
     * @param Document $document
     * @param string $status
     * @param int $verifierId
     * @return void
     */
    protected function logVerification(Document $document, string $status, int $verifierId): void
    {
        $action = $status === DocumentStatus::VERIFIED->value ? AuditAction::VERIFY_DOCUMENT->value : AuditAction::REJECT_DOCUMENT->value;
        $description = $status === DocumentStatus::VERIFIED->value
            ? "Dokumen '{$document->file_name}' diverifikasi."
            : "Dokumen '{$document->file_name}' ditolak.";

        $metadata = [
            'document_id' => $document->id,
            'document_type' => $document->document_type->value,
            'verifier_id' => $verifierId,
            'status' => $status,
        ];

        if ($document->verification_note) {
            $metadata['verification_note'] = $document->verification_note;
            $description .= " Catatan: {$document->verification_note}";
        }

        AuditLog::log(
            action: $action,
            description: $description,
            metadata: $metadata,
            modelType: ModelType::DOCUMENT->value,
            modelId: $document->id,
            userId: $verifierId
        );
    }

    /**
     * Update document metadata.
     *
     * @param Document $document
     * @param array $data
     * @param int $userId
     * @return Document
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateDocument(
        Document $document,
        array $data,
        int $userId
    ): Document {
        // Check if document can be updated (only rejected documents can be updated by uploader)
        // Manager can update any document, so we check the user role
        $user = User::find($userId);
        if ($user && $user->hasRole('uploader') && $document->status !== DocumentStatus::REJECTED) {
            $statusMessage = $document->status === DocumentStatus::VERIFIED
                ? 'Dokumen yang sudah terverifikasi tidak dapat diperbarui.'
                : 'Dokumen yang sedang menunggu verifikasi tidak dapat diperbarui.';

            throw \Illuminate\Validation\ValidationException::withMessages([
                'document' => [$statusMessage],
            ]);
        }

        // Generate new duplicate key with updated data
        $updatedData = array_merge([
            'document_type' => $document->document_type,
            'prodi' => $document->prodi,
            'tahun_ajaran' => $document->tahun_ajaran,
            'mata_kuliah' => $document->mata_kuliah,
            'kelas' => $document->kelas,
            'tahun_lulus' => $document->tahun_lulus,
            'npm' => $document->npm,
        ], $data);

        $newDuplicateKey = Document::generateDuplicateKey($updatedData);

        // Check for duplicates (excluding current document)
        if ($newDuplicateKey !== $document->duplicate_key) {
            $exists = Document::where('duplicate_key', $newDuplicateKey)
                ->where('id', '!=', $document->id)
                ->exists();

            if ($exists) {
                // Fix: Get string value from Enum for comparison and interpolation
                $documentTypeValue = $document->document_type->value;

                $message = $documentTypeValue === 'nilai'
                    ? 'Dokumen nilai dengan kombinasi tahun ajaran, prodi, mata kuliah, dan kelas yang sama sudah ada.'
                    : "Dokumen {$documentTypeValue} dengan kombinasi prodi, tahun lulus, dan NPM yang sama sudah ada.";

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'duplicate' => [$message],
                ]);
            }
        }

        DB::beginTransaction();

        try {
            // Update document metadata and reset verification status
            $updatePayload = array_merge($data, [
                'duplicate_key' => $newDuplicateKey,
                'status' => DocumentStatus::PENDING->value,
                'verified_by' => null,
                'verified_at' => null,
                'verification_note' => null,
            ]);

            $document->update($updatePayload);

            // Log update activity
            $this->logUpdate($document, $userId, $data);

            DB::commit();

            return $document->fresh(['uploader', 'verifier']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Log document update activity.
     *
     * @param Document $document
     * @param int $userId
     * @param array $updatedFields
     * @return void
     */
    protected function logUpdate(Document $document, int $userId, array $updatedFields): void
    {
        $fieldNames = implode(', ', array_keys($updatedFields));

        AuditLog::log(
            action: AuditAction::UPDATE_DOCUMENT->value,
            description: "Dokumen '{$document->file_name}' diperbarui. Field yang diubah: {$fieldNames}",
            metadata: [
                'document_id' => $document->id,
                'document_type' => $document->document_type->value,
                'updated_by' => $userId,
                'updated_fields' => $updatedFields,
                'status_reset' => DocumentStatus::PENDING->value,
            ],
            modelType: ModelType::DOCUMENT->value,
            modelId: $document->id,
            userId: $userId
        );
    }

    /**
     * Escape special LIKE wildcard characters to prevent filter bypass.
     *
     * @param string $value
     * @return string
     */
    protected function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Get document statistics.
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $total = Document::count();
        $verified = Document::where('status', DocumentStatus::VERIFIED)->count();
        $pending = Document::where('status', DocumentStatus::PENDING)->count();
        $rejected = Document::where('status', DocumentStatus::REJECTED)->count();

        $byTypeCounts = Document::select('document_type', DB::raw('count(*) as count'))
            ->groupBy('document_type')
            ->pluck('count', 'document_type')
            ->toArray();

        $byDocumentType = [
            'nilai' => (int) ($byTypeCounts['nilai'] ?? 0),
            'transkrip' => (int) ($byTypeCounts['transkrip'] ?? 0),
            'ijazah' => (int) ($byTypeCounts['ijazah'] ?? 0),
            'berita_acara_sidang' => (int) ($byTypeCounts['berita_acara_sidang'] ?? 0),
        ];

        return [
            'total_documents' => $total,
            'verified_documents' => $verified,
            'pending_documents' => $pending,
            'rejected_documents' => $rejected,
            'by_document_type' => $byDocumentType,
        ];
    }

    /**
     * Get paginated documents with filtering, search, and sorting.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listDocuments(array $filters = [], int $perPage = 15, ?int $uploaderId = null)
    {
        $query = Document::with(['uploader', 'verifier']);

        // Scope to uploader's own documents if uploader ID is provided
        if ($uploaderId !== null) {
            $query->where('uploaded_by', $uploaderId);
        }

        // Filter by document type
        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by prodi
        if (!empty($filters['prodi'])) {
            $prodi = $this->escapeLike($filters['prodi']);
            $query->where('prodi', 'like', "%{$prodi}%");
        }

        // Filter by nim / npm
        if (!empty($filters['nim']) || !empty($filters['npm'])) {
            $nim = $this->escapeLike($filters['nim'] ?? $filters['npm']);
            $query->where('npm', 'like', "%{$nim}%");
        }

        // Filter by mata_kuliah
        if (!empty($filters['mata_kuliah'])) {
            $mataKuliah = $this->escapeLike($filters['mata_kuliah']);
            $query->where('mata_kuliah', 'like', "%{$mataKuliah}%");
        }

        // Filter by tahun_akademik / tahun_ajaran
        if (!empty($filters['tahun_akademik']) || !empty($filters['tahun_ajaran'])) {
            $tahun = $this->escapeLike($filters['tahun_akademik'] ?? $filters['tahun_ajaran']);
            $query->where('tahun_ajaran', 'like', "%{$tahun}%");
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Search by all fields
        if (!empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('tahun_ajaran', 'like', "%{$search}%")
                    ->orWhere('mata_kuliah', 'like', "%{$search}%")
                    ->orWhere('npm', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        // Sorting
        $allowedSorts = ['created_at', 'updated_at', 'tahun_lulus', 'status', 'document_type', 'prodi', 'file_name'];
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        return $query->orderBy($sortBy, $sortDirection)->paginate($perPage);
    }

    /**
     * Get paginated soft-deleted (trashed) documents.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTrashedDocuments(array $filters = [], int $perPage = 15)
    {
        $query = Document::onlyTrashed()->with(['uploader', 'verifier']);

        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (!empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('npm', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('deleted_at', 'desc')->paginate($perPage);
    }

    /**
     * Restore a soft-deleted document.
     *
     * @param int $id
     * @param int $userId
     * @return Document
     */
    public function restoreDocument(int $id, int $userId): Document
    {
        $document = Document::onlyTrashed()->findOrFail($id);

        // Regenerate original duplicate key
        $docType = $document->document_type instanceof \BackedEnum ? $document->document_type->value : $document->document_type;
        $prodi = $document->prodi instanceof \BackedEnum ? $document->prodi->value : $document->prodi;

        $originalKey = Document::generateDuplicateKey([
            'document_type' => $docType,
            'prodi' => $prodi,
            'tahun_ajaran' => $document->tahun_ajaran,
            'mata_kuliah' => $document->mata_kuliah,
            'kelas' => $document->kelas,
            'tahun_lulus' => $document->tahun_lulus,
            'npm' => $document->npm,
        ]);

        // Check if an active document with the same duplicate key already exists
        $existing = Document::where('duplicate_key', $originalKey)->first();
        if ($existing) {
            throw new ConflictHttpException('Tidak dapat memulihkan dokumen karena sudah ada dokumen aktif dengan data yang sama.');
        }

        $document->update(['duplicate_key' => $originalKey]);
        $document->restore();

        AuditLog::log(
            action: AuditAction::UPDATE_DOCUMENT->value,
            description: "Dokumen '{$document->file_name}' berhasil dipulihkan dari tempat sampah.",
            metadata: [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'restored_by' => $userId,
            ],
            modelType: ModelType::DOCUMENT->value,
            modelId: $document->id,
            userId: $userId
        );

        return $document->fresh(['uploader', 'verifier']);
    }
}


