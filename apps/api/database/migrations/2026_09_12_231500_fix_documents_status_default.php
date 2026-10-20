<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Align the documents.status column default with the canonical
     * DocumentStatus enum value (App\Enums\DocumentStatus::PENDING).
     *
     * The original 2026_01_05_110000_create_documents_table migration used
     * 'menunggu_verifikasi' (underscore) which does NOT match the enum value
     * 'menunggu verifikasi' (space). The application layer always sets the
     * status explicitly on creation (DocumentService::uploadDocument), so the
     * DB default is effectively unreachable at runtime — but we correct it
     * here for schema consistency and to avoid confusion.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('status', 50)->default('menunggu verifikasi')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('status', 50)->default('menunggu_verifikasi')->change();
        });
    }
};
