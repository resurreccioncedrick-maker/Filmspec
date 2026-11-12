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
            $table->boolean('driver_required')->default(false)->after('vehicle_rate_id');
        });

        DB::table('bookings')->whereNotNull('vehicle_rate_id')->update(['driver_required' => true]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('driver_required');
        });
    }
};
