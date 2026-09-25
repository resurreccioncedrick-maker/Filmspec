<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * "No transport assigned" and "nobody has decided about transport yet" used to look identical
 * (vehicle_rate_id/transportation_cost both null/zero either way), so a booking could reach
 * 4/4 "ready to release" without staff ever opening the Assign Transport form. This column is
 * set the moment that form is actually submitted (whatever the outcome — a real vehicle or an
 * explicit "no transport needed"), and the release gate requires it to be set at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('transport_confirmed_at')->nullable()->after('driver_required');
        });

        // Bookings already past 'confirmed' were already released under the old rule (which had
        // no such gate) — backfill so this new requirement doesn't retroactively block history
        // that already went out the door. Anything still sitting at 'pending'/'confirmed' is
        // correctly left null: that's exactly the case this column exists to catch going forward.
        DB::table('bookings')
            ->whereIn('booking_status', ['ongoing', 'pending_inspection', 'returned', 'completed'])
            ->update(['transport_confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('transport_confirmed_at');
        });
    }
};
