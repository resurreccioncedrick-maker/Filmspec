<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same shape as equipment_units — genuinely new, no prior per-unit accessory tracking
        // existed (only the aggregate accessories.quantity).
        Schema::create('accessory_units', function (Blueprint $table) {
            $table->id('unit_id');
            $table->integer('accessory_id');
            $table->string('asset_tag', 50)->unique();
            $table->string('serial_no', 100)->nullable();
            $table->enum('condition', ['excellent', 'good', 'serviceable', 'damaged'])->default('good');
            $table->enum('status', ['available', 'allocated', 'in_field', 'inspection_pending', 'under_maintenance', 'retired'])->default('available');
            $table->string('location', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('accessory_id')->references('accessory_id')->on('accessories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessory_units');
    }
};
