<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_rates', function (Blueprint $table) {
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
