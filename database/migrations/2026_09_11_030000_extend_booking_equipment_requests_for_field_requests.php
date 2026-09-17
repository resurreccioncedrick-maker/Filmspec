<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * booking_equipment_requests has existed in the live schema with no migration (like several
 * other legacy tables — see 2026_09_05_000000_add_vehicle_rate_fk_to_bookings.php's note).
 * This evolves it from an equipment-only "extra item" request into a general field-request
 * table covering equipment, accessories, and crew, with a Dispatched/Delivered tail added to
 * its lifecycle — one request table, not three parallel ones.
 *
 * No doctrine/dbal in this project, so the existing status enum and equipment_id's NOT NULL
 * constraint are altered via raw SQL rather than Schema::table(...)->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE booking_equipment_requests
            MODIFY status ENUM('pending','approved','rejected','dispatched','delivered') NOT NULL DEFAULT 'pending'");

        DB::statement('ALTER TABLE booking_equipment_requests MODIFY equipment_id INT NULL');

        Schema::table('booking_equipment_requests', function (Blueprint $table) {
            $table->enum('item_type', ['equipment', 'accessory', 'crew'])->default('equipment')->after('equipment_id');
            $table->integer('accessory_id')->nullable()->after('item_type');
            $table->integer('crew_id')->nullable()->after('accessory_id');
            $table->integer('position_id')->nullable()->after('crew_id');
            $table->integer('vehicle_rate_id')->nullable()->after('admin_notes');
            $table->integer('driver_crew_id')->nullable()->after('vehicle_rate_id');
            $table->dateTime('eta')->nullable()->after('driver_crew_id');
            $table->dateTime('delivered_at')->nullable()->after('eta');
        });
    }

    public function down(): void
    {
        Schema::table('booking_equipment_requests', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'accessory_id', 'crew_id', 'position_id', 'vehicle_rate_id', 'driver_crew_id', 'eta', 'delivered_at']);
        });

        DB::statement("ALTER TABLE booking_equipment_requests
            MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");

        DB::statement('ALTER TABLE booking_equipment_requests MODIFY equipment_id INT NOT NULL');
    }
};
