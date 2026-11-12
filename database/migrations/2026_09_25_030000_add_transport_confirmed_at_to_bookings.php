<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('transport_confirmed_at')->nullable()->after('driver_required');
        });

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
