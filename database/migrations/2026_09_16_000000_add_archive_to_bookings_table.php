<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Soft-delete for pending/cancelled bookings — replaces the old hard-delete so
            // audit history (activity_logs, related line-item tables) is never destroyed.
            $table->boolean('is_archived')->default(false)->after('booking_status');
            $table->dateTime('archived_at')->nullable()->after('is_archived');
            $table->integer('archived_by')->nullable()->after('archived_at');
            $table->foreign('archived_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropIndex(['is_archived']);
            $table->dropColumn(['is_archived', 'archived_at', 'archived_by']);
        });
    }
};
