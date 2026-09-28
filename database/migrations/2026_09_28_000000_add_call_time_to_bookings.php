<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Log Attendance form's Shoot Information header needs a Scheduled Call Time
 * alongside Booking / Shoot Date / Location, but nothing in the schema tracked one —
 * shoot_date_start only carries a date. Nullable so existing bookings just show
 * "Not set" until staff fill it in from the attendance page itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('call_time')->nullable()->after('shoot_location');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('call_time');
        });
    }
};
