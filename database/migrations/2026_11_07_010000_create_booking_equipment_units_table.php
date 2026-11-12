<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_equipment_units', function (Blueprint $table) {
            $table->id('id');
            $table->integer('bk_equip_id');
            $table->unsignedBigInteger('equipment_unit_id');
            $table->timestamp('allocated_at')->useCurrent();

            $table->unique(['bk_equip_id', 'equipment_unit_id']);
            $table->index('equipment_unit_id');
            $table->foreign('bk_equip_id')->references('bk_equip_id')->on('booking_equipment')->onDelete('cascade');
            $table->foreign('equipment_unit_id')->references('unit_id')->on('equipment_units')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_equipment_units');
    }
};
