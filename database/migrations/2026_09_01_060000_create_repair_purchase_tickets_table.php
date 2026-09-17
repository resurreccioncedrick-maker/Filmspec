<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_purchase_tickets', function (Blueprint $table) {
            $table->increments('ticket_id');
            $table->string('ticket_number', 20)->unique();
            $table->enum('type', ['repair', 'purchase'])->default('repair');
            $table->string('title', 200);
            $table->integer('equipment_id')->nullable();
            $table->enum('status', ['requested', 'approved', 'in_progress', 'completed', 'cancelled'])->default('requested');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('vendor_supplier', 150)->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->date('requested_date');
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('person_in_charge', 150)->nullable();
            $table->text('note')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->foreign('equipment_id')->references('equipment_id')->on('equipment')->onDelete('set null');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_purchase_tickets');
    }
};
