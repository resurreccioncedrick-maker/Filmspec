<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A dedicated table for password-reset OTPs, kept separate from mfa_tokens
        // so a pending login (MFA) and a pending password reset can never collide
        // and invalidate each other.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('token_hash', 255);
            $table->dateTime('expires_at');
            $table->boolean('used')->default(false);
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
