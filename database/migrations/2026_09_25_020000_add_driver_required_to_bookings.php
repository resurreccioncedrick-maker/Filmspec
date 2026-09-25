<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Whether a booking's FilmSpec transport needs a driver isn't implied by transport being
 * assigned at all — some transport is just a delivery/courier fee with no FilmSpec driver
 * involved. Staff decide this explicitly on the Assign Transport form; the release-readiness
 * gate reads it instead of assuming every transport-using booking needs a driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('driver_required')->default(false)->after('vehicle_rate_id');
        });

        // New column defaults to false, which would silently un-block any existing booking that
        // was already correctly gated on having a driver under the old (always-required) rule.
        // Backfill true for every booking that already has transport assigned, preserving today's
        // behavior — staff can uncheck it per booking going forward via Assign Transport.
        DB::table('bookings')->whereNotNull('vehicle_rate_id')->update(['driver_required' => true]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('driver_required');
        });
    }
};
