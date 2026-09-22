<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_unavailability', function (Blueprint $table) {
            // Additive — the existing free-text 'reason' column is kept as-is (still shown to
            // scheduling staff); reason_category is a new, optional structured classification
            // alongside it, and internal_note is a new staff-only field. Only 2 existing rows,
            // both left with category/note NULL rather than guessing a backfill value.
            $table->string('reason_category', 50)->nullable()->after('reason');
            $table->text('internal_note')->nullable()->after('reason_category');
        });
    }

    public function down(): void
    {
        Schema::table('crew_unavailability', function (Blueprint $table) {
            $table->dropColumn(['reason_category', 'internal_note']);
        });
    }
};
