<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->increments('acceptance_id');
            $table->integer('user_id');
            $table->integer('booking_id')->nullable();
            $table->string('tc_version', 20);
            $table->string('pp_version', 20);
            $table->dateTime('accepted_at');
            $table->string('ip_address', 45)->nullable();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('set null');
            $table->index('user_id');
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
    }
};
