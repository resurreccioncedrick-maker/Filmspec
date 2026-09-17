<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital contract e-signature (Part 6) builds on the existing "documents" table (category
 * already includes "contract") rather than a parallel signature system. Only meaningful where
 * category = 'contract' — left nullable and unenforced elsewhere, same discipline the table
 * already uses for booking_id/client_id being "exactly one, checked in the controller."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dateTime('signed_at')->nullable()->after('note');
            $table->integer('signed_by')->nullable()->after('signed_at');
            $table->text('signature_data')->nullable()->after('signed_by');
            $table->enum('signature_type', ['drawn', 'typed'])->nullable()->after('signature_data');

            $table->foreign('signed_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['signed_by']);
            $table->dropColumn(['signed_at', 'signed_by', 'signature_data', 'signature_type']);
        });
    }
};
