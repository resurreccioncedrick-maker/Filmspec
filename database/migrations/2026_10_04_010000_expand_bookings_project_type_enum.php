<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY project_type ENUM('commercial','indie_film','tv_network','music_video','interview','other','film','documentary','event','corporate') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY project_type ENUM('commercial','indie_film','tv_network','music_video','interview','other') NULL");
    }
};
