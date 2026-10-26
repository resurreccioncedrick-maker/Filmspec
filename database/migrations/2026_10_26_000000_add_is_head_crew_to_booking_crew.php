<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Head Crew mark — per booking, not per person. Staff mark ONE crew member on a booking as
 * its Head Crew; only that person gets Attendance and Equipment Checklist (Confirm Arrival /
 * Check-In) in the crew portal. Deliberately independent of the crew position (a position
 * can literally be named "Head Crew", but any position can be the head). Internal only —
 * never read by the client-facing cost estimate, CE PDF or client booking pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_crew', function (Blueprint $table) {
            $table->boolean('is_head_crew')->default(false)->after('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_crew', function (Blueprint $table) {
            $table->dropColumn('is_head_crew');
        });
    }
};
