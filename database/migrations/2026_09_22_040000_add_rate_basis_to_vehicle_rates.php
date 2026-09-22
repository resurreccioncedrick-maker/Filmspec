<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_rates', function (Blueprint $table) {
            // All existing vehicle types are priced per trip today (the only basis the app has
            // ever used), so 'per_trip' is a safe, behavior-preserving default for every
            // existing row — this is a pure addition, base_rate itself is untouched.
            $table->enum('rate_basis', ['per_trip', 'per_day', 'round_trip'])->default('per_trip')->after('base_rate');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_rates', function (Blueprint $table) {
            $table->dropColumn('rate_basis');
        });
    }
};
