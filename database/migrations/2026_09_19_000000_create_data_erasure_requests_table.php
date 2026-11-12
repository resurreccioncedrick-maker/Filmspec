<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
