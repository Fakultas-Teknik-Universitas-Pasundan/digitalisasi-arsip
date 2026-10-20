<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the documents.status column default.
 *
 * Migration 2026_01_05_110000_create_documents_table used 'menunggu_verifikasi'
 * (underscore) while the canonical DocumentStatus::PENDING value is
 * 'menunggu verifikasi' (space). The DB default was corrected in migration
 * 2026_09_12_231500_fix_documents_status_default.
 *
 * These tests intentionally bypass the Eloquent layer (raw DB insert) so the
 * column default — not DocumentService — is the value under test.
 */
class DocumentStatusDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function insertWithoutStatus(): int
    {
        $user = User::factory()->create(['role' => 'uploader']);

        return DB::table('documents')->insertGetId([
            'document_type' => 'nilai',
            'duplicate_key' => sha1('status-default-probe-'.uniqid('', true)),
            'file_path' => 'archives/nilai/probe.pdf',
            'file_name' => 'probe.pdf',
            'file_size' => 12345,
            'prodi' => 'Teknik Informatika',
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_default_status_matches_canonical_enum_value(): void
    {
        $id = $this->insertWithoutStatus();

        $status = DB::table('documents')->where('id', $id)->value('status');

        $this->assertSame(
            'menunggu verifikasi',
            $status,
            'documents.status default must equal the canonical DocumentStatus::PENDING value.'
        );
    }

    public function test_database_default_status_is_castable_to_document_status_enum(): void
    {
        $id = $this->insertWithoutStatus();

        $status = DB::table('documents')->where('id', $id)->value('status');

        // The default must be a value the enum can actually parse.
        $enum = DocumentStatus::tryFrom((string) $status);

        $this->assertNotNull(
            $enum,
            "documents.status default '{$status}' is not a valid DocumentStatus enum value."
        );
        $this->assertSame(DocumentStatus::PENDING, $enum);
    }

    public function test_default_status_does_not_contain_legacy_underscore_variant(): void
    {
        $id = $this->insertWithoutStatus();

        $status = DB::table('documents')->where('id', $id)->value('status');

        $this->assertNotSame('menunggu_verifikasi', $status);
    }
}
