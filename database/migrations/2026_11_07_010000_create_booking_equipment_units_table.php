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
        Schema::create('booking_equipment_units', function (Blueprint $table) {
            $table->id('id');
            // bk_equip_id is booking_equipment's PK — this pivot ties a specific physical unit
            // to one line of a booking's equipment (one booking_equipment row can have several
            // of these when quantity > 1).
            $table->integer('bk_equip_id');
            $table->unsignedBigInteger('equipment_unit_id');
            $table->timestamp('allocated_at')->useCurrent();

            $table->unique(['bk_equip_id', 'equipment_unit_id']);
            $table->index('equipment_unit_id');
            $table->foreign('bk_equip_id')->references('bk_equip_id')->on('booking_equipment')->onDelete('cascade');
            $table->foreign('equipment_unit_id')->references('unit_id')->on('equipment_units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_equipment_units');
    }
};
