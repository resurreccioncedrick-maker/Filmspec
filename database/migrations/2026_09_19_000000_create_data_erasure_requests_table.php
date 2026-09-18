<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the Data Privacy Act (R.A. 10173) right-to-erasure flow: a client requests erasure
 * of their personal data from their own Account page, staff review and action it from
 * Data Retention. "Erasure" here means anonymizing identifying fields on users/clients —
 * booking, payment, and other financial/legal records are deliberately kept intact (see
 * App\Support\ClientErasure) rather than deleted, since those need to be retained for
 * accounting/legal reasons regardless of a data-subject's erasure request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_erasure_requests', function (Blueprint $table) {
            $table->increments('request_id');
            $table->integer('client_id');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->integer('processed_by')->nullable();
            $table->datetime('processed_at')->nullable();
            $table->text('staff_notes')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('client_id')->on('clients')->onDelete('cascade');
            $table->foreign('processed_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_erasure_requests');
    }
};
