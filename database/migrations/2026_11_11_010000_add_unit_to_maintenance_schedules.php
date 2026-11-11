<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table) {
            // Nullable — maintenance can still be scheduled against the equipment model alone
            // for gear with no individually-tracked units, same convention incident_reports
            // already uses for equipment_unit_id.
            $table->unsignedBigInteger('equipment_unit_id')->nullable()->after('equipment_id');
            $table->foreign('equipment_unit_id')->references('unit_id')->on('equipment_units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_schedules', function (Blueprint $table) {
            $table->dropForeign(['equipment_unit_id']);
            $table->dropColumn('equipment_unit_id');
        });
    }
};
