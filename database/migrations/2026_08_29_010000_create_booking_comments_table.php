<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_comments', function (Blueprint $table) {
            $table->increments('comment_id');
            $table->integer('booking_id');
            $table->integer('user_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_comments');
    }
};
