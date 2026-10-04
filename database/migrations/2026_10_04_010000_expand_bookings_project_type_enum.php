<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bug: the client-facing booking form (ce-preview.blade.php "Project Type"
        // dropdown) offers 'film', 'documentary', 'event', 'corporate' — values that
        // were never added to this column's enum (which only ever matched the
        // separate staff-facing form's vocabulary: commercial/indie_film/tv_network/
        // music_video/interview/other). Any client submitting one of those four
        // types hit MySQL strict-mode error 1265 ("Data truncated for column
        // 'project_type'") and the whole booking insert failed. Purely additive —
        // existing values and rows are untouched.
        DB::statement("ALTER TABLE bookings MODIFY project_type ENUM('commercial','indie_film','tv_network','music_video','interview','other','film','documentary','event','corporate') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY project_type ENUM('commercial','indie_film','tv_network','music_video','interview','other') NULL");
    }
};
