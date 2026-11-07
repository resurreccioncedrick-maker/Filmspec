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
        Schema::create('crew_profile_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->integer('crew_id');
            // Non-password field changes, e.g. {"phone":{"old":"...","new":"..."},"email":{...}}
            // — never contains a password field; password is a separate pair of columns below so
            // a plaintext/hash value is never embedded in a JSON blob an admin's UI might render.
            $table->json('changes')->nullable();
            $table->boolean('password_change_requested')->default(false);
            $table->string('new_password_hash', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 255)->nullable();

            $table->index('crew_id');
            $table->index('status');
            $table->foreign('crew_id')->references('crew_id')->on('crew_members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_profile_requests');
    }
};
