<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->id('fleet_vehicle_id');
            $table->integer('vehicle_type_id');
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('plate_no', 20)->unique();
            $table->year('year')->nullable();
            $table->string('color', 50)->nullable();
            $table->enum('status', ['available', 'assigned', 'maintenance', 'out_of_service'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_type_id')->references('vehicle_id')->on('vehicle_rates')->restrictOnDelete();
        });

        Schema::create('transport_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->integer('booking_id');
            $table->unsignedBigInteger('fleet_vehicle_id');
            $table->integer('driver_crew_id')->nullable();
            $table->date('dispatch_date')->nullable();
            $table->time('departure_time')->nullable();
            $table->string('destination', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('booking_id')->on('bookings')->cascadeOnDelete();
            $table->foreign('fleet_vehicle_id')->references('fleet_vehicle_id')->on('fleet_vehicles')->restrictOnDelete();
            $table->foreign('driver_crew_id')->references('crew_id')->on('crew_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_assignments');
        Schema::dropIfExists('fleet_vehicles');
    }
};
