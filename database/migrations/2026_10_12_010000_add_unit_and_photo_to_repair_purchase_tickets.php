<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_purchase_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_unit_id')->nullable()->after('equipment_id');
            $table->foreign('equipment_unit_id')->references('unit_id')->on('equipment_units')->nullOnDelete();
            $table->string('photo_path', 255)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('repair_purchase_tickets', function (Blueprint $table) {
            $table->dropForeign(['equipment_unit_id']);
            $table->dropColumn(['equipment_unit_id', 'photo_path']);
        });
    }
};
