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
            if (!Schema::hasColumn('documents', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('prodis', function (Blueprint $table) {
            if (!Schema::hasColumn('prodis', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            if (!Schema::hasColumn('document_types', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('prodis', function (Blueprint $table) {
            if (Schema::hasColumn('prodis', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
