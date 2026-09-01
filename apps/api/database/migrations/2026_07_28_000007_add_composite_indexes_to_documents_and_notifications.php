<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['document_type', 'status', 'prodi'], 'idx_docs_type_status_prodi');
            $table->index(['tahun_ajaran', 'mata_kuliah'], 'idx_docs_ta_mk');
            $table->index(['tahun_lulus', 'npm'], 'idx_docs_tl_npm');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at', 'created_at'], 'idx_notifs_user_read_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_docs_type_status_prodi');
            $table->dropIndex('idx_docs_ta_mk');
            $table->dropIndex('idx_docs_tl_npm');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifs_user_read_created');
        });
    }
};
