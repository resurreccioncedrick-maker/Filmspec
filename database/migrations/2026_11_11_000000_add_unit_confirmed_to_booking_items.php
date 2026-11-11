<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_equipment', function (Blueprint $table) {
            $table->boolean('unit_confirmed')->default(false)->after('notes');
        });
        Schema::table('booking_accessories', function (Blueprint $table) {
            $table->boolean('unit_confirmed')->default(false)->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_equipment', function (Blueprint $table) {
            $table->dropColumn('unit_confirmed');
        });
        Schema::table('booking_accessories', function (Blueprint $table) {
            $table->dropColumn('unit_confirmed');
        });
    }
};
