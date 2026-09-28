<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Return & Inspection Integration for accessories — ChecklistController's saveChecklist()
 * already tracks a structured per-accessory condition-in/out checklist via
 * equipment_checklist.accessory_id (see add_accessory_to_equipment_checklist), it just never
 * created an incident for a damaged/missing accessory the way it does for equipment, because
 * incident_reports had nowhere to point that incident at besides an equipment_id. Same
 * nullable-equipment_id + accessory_id pattern as that earlier migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->integer('equipment_id')->nullable()->change();
            $table->integer('accessory_id')->nullable()->after('equipment_id');
            $table->foreign('accessory_id')->references('accessory_id')->on('accessories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropForeign(['accessory_id']);
            $table->dropColumn('accessory_id');
            $table->integer('equipment_id')->nullable(false)->change();
        });
    }
};
