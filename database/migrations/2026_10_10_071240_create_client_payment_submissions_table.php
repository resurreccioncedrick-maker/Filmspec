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
        Schema::create('client_payment_submissions', function (Blueprint $table) {
            $table->id('submission_id');
            $table->integer('booking_id');
            $table->integer('submitted_by');
            $table->enum('payment_type', ['downpayment', 'progress', 'final']);
            $table->enum('payment_method', ['cash', 'gcash', 'bank_transfer']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_number', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('proof_of_payment_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->integer('resulting_payment_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'status']);
            $table->index('status');
            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('cascade');
            $table->foreign('submitted_by')->references('user_id')->on('users');
            $table->foreign('reviewed_by')->references('user_id')->on('users');
            $table->foreign('resulting_payment_id')->references('payment_id')->on('payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payment_submissions');
    }
};
