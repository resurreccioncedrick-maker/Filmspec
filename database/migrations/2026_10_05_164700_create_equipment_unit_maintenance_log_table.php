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
        Schema::create('equipment_unit_maintenance_log', function (Blueprint $table) {
            $table->id('log_id');
            $table->unsignedBigInteger('unit_id');
            $table->enum('action', ['mark', 'return']);
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('performed_by')->nullable();
            $table->timestamp('performed_at')->useCurrent();

            $table->index('unit_id');
            $table->foreign('unit_id')->references('unit_id')->on('equipment_units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_unit_maintenance_log');
    }
};
