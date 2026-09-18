<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the e-signature capability and the "contract" document category — the app has no
 * use for it. Existing contract-tagged documents are recategorized to "other" first so no
 * uploaded files are lost, only the signing feature and the category label.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')->where('category', 'contract')->update(['category' => 'other']);

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['signed_by']);
            $table->dropColumn(['signed_at', 'signed_by', 'signature_data', 'signature_type']);
        });

        DB::statement("ALTER TABLE documents MODIFY category ENUM('id','permit','other') NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE documents MODIFY category ENUM('contract','id','permit','other') NOT NULL DEFAULT 'other'");

        Schema::table('documents', function (Blueprint $table) {
            $table->dateTime('signed_at')->nullable()->after('note');
            $table->integer('signed_by')->nullable()->after('signed_at');
            $table->text('signature_data')->nullable()->after('signed_by');
            $table->enum('signature_type', ['drawn', 'typed'])->nullable()->after('signature_data');

            $table->foreign('signed_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }
};
