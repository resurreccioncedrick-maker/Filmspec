<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_accessory_units', function (Blueprint $table) {
            $table->id('id');
            $table->integer('ba_id');
            $table->unsignedBigInteger('accessory_unit_id');
            $table->timestamp('allocated_at')->useCurrent();

            $table->unique(['ba_id', 'accessory_unit_id']);
            $table->index('accessory_unit_id');
            $table->foreign('ba_id')->references('ba_id')->on('booking_accessories')->onDelete('cascade');
            $table->foreign('accessory_unit_id')->references('unit_id')->on('accessory_units')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_accessory_units');
    }
};
