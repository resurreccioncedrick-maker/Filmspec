<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Genuinely new — vehicle_rates only ever modeled vehicle TYPES/pricing, never
        // individual physical vehicles. fleet_vehicle_id is deliberately not named vehicle_id,
        // to avoid colliding in meaning with vehicle_rates.vehicle_id (a vehicle TYPE's PK).
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

        // Assign Transport (Booking Detail) keeps writing bookings.vehicle_rate_id exactly as it
        // does today (untouched, per the "keep current process" rule) — this table is the
        // richer record layered on top when a specific fleet vehicle + driver + dispatch details
        // are actually assigned, not a replacement of that existing simple field.
        Schema::create('transport_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->integer('booking_id');
            // fleet_vehicles.fleet_vehicle_id is a Laravel id() (bigint unsigned) — match that
            // exact type here, not the plain signed int() the rest of this legacy schema uses,
            // or the FK add fails on a type/signedness mismatch (same class of bug as the
            // payments/equipment_units migrations earlier).
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
