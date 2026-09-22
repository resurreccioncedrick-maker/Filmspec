<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive, alongside the existing free-text 'reason' column (kept as-is).
        Schema::table('crew_attendance', function (Blueprint $table) {
            $table->string('reason_category', 50)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('crew_attendance', function (Blueprint $table) {
            $table->dropColumn('reason_category');
        });
    }
};
