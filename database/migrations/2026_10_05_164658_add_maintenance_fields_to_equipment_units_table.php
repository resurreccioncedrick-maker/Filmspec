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
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->string('maintenance_reason', 255)->nullable()->after('notes');
            $table->date('maintenance_date')->nullable()->after('maintenance_reason');
            $table->text('maintenance_notes')->nullable()->after('maintenance_date');
            $table->date('expected_return_date')->nullable()->after('maintenance_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->dropColumn(['maintenance_reason', 'maintenance_date', 'maintenance_notes', 'expected_return_date']);
        });
    }
};
