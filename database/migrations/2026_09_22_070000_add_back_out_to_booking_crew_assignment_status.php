<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance already has a 'back_out' status (crew backed out of a confirmed shoot) but
        // booking_crew.assignment_status had no matching value, so a back-out logged in Attendance
        // never changed the crew member's actual assignment state on the booking. Additive enum value,
        // only 38 existing rows (all 'tentative'/'confirmed'), no backfill needed.
        DB::statement("ALTER TABLE booking_crew MODIFY assignment_status ENUM('tentative','confirmed','declined','no_show','replaced','back_out') NOT NULL DEFAULT 'tentative'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE booking_crew MODIFY assignment_status ENUM('tentative','confirmed','declined','no_show','replaced') NOT NULL DEFAULT 'tentative'");
    }
};
