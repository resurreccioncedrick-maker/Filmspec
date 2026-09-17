<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Confirmed dead in an earlier database audit this session — no code (legacy or
     * Laravel) reads or writes any of these. `database/filmspec_database.sql` and
     * `tools/setup.php` (now deleted) already had their CREATE statements removed;
     * the live tables/views themselves were never actually dropped until now.
     *
     * `sessions` is deliberately NOT included here even though it was flagged dead in
     * that same audit — the live schema (`id`, `created_at`, `updated_at` only) doesn't
     * match what Laravel's `SESSION_DRIVER=database` expects (`payload`,
     * `last_activity`, `user_id`, ...), but empirically nothing writes to it and
     * dropping the wrong `sessions` table could break live login sessions. Left alone
     * pending a real answer to where sessions actually persist.
     */
    public function up(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('crew_skills');
        Schema::dropIfExists('crew_work_history');

        foreach (['v_active_bookings', 'v_crew_performance', 'v_equipment_availability', 'v_monthly_revenue'] as $view) {
            DB::statement("DROP VIEW IF EXISTS `$view`");
        }
    }

    public function down(): void
    {
        // Confirmed-dead objects with no data worth preserving — no-op down().
    }
};
