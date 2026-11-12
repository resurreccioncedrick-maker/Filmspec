<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_unavailability', function (Blueprint $table) {
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
