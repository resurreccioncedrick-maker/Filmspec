<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->date('date_acquired')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_units', function (Blueprint $table) {
            $table->dropColumn('date_acquired');
        });
    }
};
