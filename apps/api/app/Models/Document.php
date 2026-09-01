<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Prodi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'document_type',
        'duplicate_key',
        'file_path',
        'file_name',
        'file_size',
        'prodi',
        'tahun_ajaran',
        'mata_kuliah',
        'kelas',
        'tahun_lulus',
        'npm',
        'status',
        'verification_note',
        'uploaded_by',
        'verified_by',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'prodi' => Prodi::class,
            'file_size' => 'integer',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user who uploaded this document.
     *
     * @return BelongsTo
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who verified this document.
     *
     * @return BelongsTo
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope to filter by document type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope to filter by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get only verified documents.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->where('status', DocumentStatus::VERIFIED);
    }

    /**
     * Scope to get only pending verification documents.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', DocumentStatus::PENDING);
    }

    /**
     * Check if document is verified.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->status === DocumentStatus::VERIFIED;
    }

    /**
     * Check if document is pending verification.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === DocumentStatus::PENDING;
    }

    /**
     * Check if document is rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::REJECTED;
    }

    /**
     * Generate duplicate key from document metadata.
     *
     * @param array $data
     * @return string
     */
    public static function generateDuplicateKey(array $data): string
    {
        // Extract string value from Enum if needed
        $documentType = is_string($data['document_type'])
            ? $data['document_type']
            : $data['document_type']->value;

        $prodi = is_string($data['prodi'])
            ? $data['prodi']
            : $data['prodi']->value;

        if ($documentType === 'nilai') {
            // nilai: sha1("nilai|tahun_ajaran|prodi|mata_kuliah|kelas")
            $key = implode('|', [
                'nilai',
                $data['tahun_ajaran'],
                $prodi,
                $data['mata_kuliah'],
                $data['kelas'],
            ]);
        } else {
            // ijazah/transkrip/berita_acara_sidang: sha1("type|prodi|tahun_lulus|npm")
            $key = implode('|', [
                $documentType,
                $prodi,
                $data['tahun_lulus'],
                $data['npm'],
            ]);
        }

        return sha1($key);
    }
}
