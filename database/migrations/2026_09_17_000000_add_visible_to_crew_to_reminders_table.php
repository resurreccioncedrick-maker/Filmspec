<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            // Lets staff flag a reminder as a crew-facing announcement, surfaced on the
            // Crew Portal's Today tab — same reminder tool staff already use, just an
            // extra visibility toggle rather than a whole separate broadcast feature.
            $table->boolean('visible_to_crew')->default(false)->after('is_done');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('visible_to_crew');
        });
    }
};
