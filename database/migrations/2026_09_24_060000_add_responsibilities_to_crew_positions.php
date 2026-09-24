<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_positions', function (Blueprint $table) {
            // One responsibility per line — kept separate from `description` (a short summary)
            // so each can be displayed differently later without re-parsing a combined blob.
            $table->text('responsibilities')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('crew_positions', function (Blueprint $table) {
            $table->dropColumn('responsibilities');
        });
    }
};
