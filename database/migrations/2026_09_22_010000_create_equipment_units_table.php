<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_units', function (Blueprint $table) {
            $table->id('unit_id');
            $table->integer('equipment_id');
            $table->string('asset_tag', 50)->unique();
            $table->string('serial_no', 100)->nullable();
            $table->enum('condition', ['excellent', 'good', 'serviceable', 'damaged'])->default('good');
            $table->enum('status', ['available', 'allocated', 'in_field', 'inspection_pending', 'under_maintenance', 'retired'])->default('available');
            $table->string('location', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('equipment_id')->references('equipment_id')->on('equipment')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_units');
    }
};
