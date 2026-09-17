<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blue Book (a generic equipment/crew/outsourced rate-reference list) removed at
     * the user's request — redundant with rates already tracked on the actual
     * equipment/crew/vehicle_rates records. Confirmed zero rows and no other code
     * reading blue_book_entries before dropping.
     */
    public function up(): void
    {
        Schema::dropIfExists('blue_book_entries');
    }

    public function down(): void
    {
        // Empty table, no data worth preserving — no-op down().
    }
};
