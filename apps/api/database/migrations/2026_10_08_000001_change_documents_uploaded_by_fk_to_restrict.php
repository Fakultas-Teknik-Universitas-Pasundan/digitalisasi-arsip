<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting an uploader must never delete the institutional documents
     * they uploaded. Change the FK from CASCADE to RESTRICT; user deletion
     * is blocked in UserService while the user still owns documents.
     */
    public function up(): void
    {
        // SQLite (test connection) cannot drop foreign keys by name; there
        // the same rule is enforced by the UserService deletion guard.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign('documents_uploaded_by_foreign');
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign('documents_uploaded_by_foreign');
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
