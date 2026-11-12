<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE booking_crew MODIFY assignment_status ENUM('tentative','confirmed','declined','no_show','replaced','back_out') NOT NULL DEFAULT 'tentative'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE booking_crew MODIFY assignment_status ENUM('tentative','confirmed','declined','no_show','replaced') NOT NULL DEFAULT 'tentative'");
    }
};
