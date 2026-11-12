<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $indexExists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'bookings_vehicle_rate_id_index'"
        )->c;
        $fkExists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'
               AND COLUMN_NAME = 'vehicle_rate_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
        )->c;

        if ($indexExists || $fkExists) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('vehicle_rate_id')
                ->references('vehicle_id')->on('vehicle_rates')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['vehicle_rate_id']);
        });
    }
};
