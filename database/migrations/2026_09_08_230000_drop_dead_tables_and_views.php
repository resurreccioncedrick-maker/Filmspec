<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
    }
};
