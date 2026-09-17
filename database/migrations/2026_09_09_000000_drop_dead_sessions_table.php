<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split out from 2026_09_08_230000_drop_dead_tables_and_views.php for a real
     * answer instead of a guess: `sessions`' live schema (id, created_at, updated_at
     * only) doesn't match Laravel's database session driver, which was reason enough
     * to hold off. Confirmed via .env: SESSION_TABLE=laravel_sessions — Laravel has
     * been writing real, active session data to `laravel_sessions` (125 rows) all
     * along. `sessions` is a same-name-coincidence leftover from something else
     * entirely (likely the legacy app, per the original dead-table audit), genuinely
     * unused by anything.
     */
    public function up(): void
    {
        Schema::dropIfExists('sessions');
    }

    public function down(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
