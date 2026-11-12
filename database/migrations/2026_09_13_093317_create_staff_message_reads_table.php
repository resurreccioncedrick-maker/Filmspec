<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_message_reads', function (Blueprint $table) {
            $table->increments('read_id');
            $table->integer('user_id');
            $table->enum('thread_type', ['support', 'booking', 'ticket']);
            $table->integer('thread_id');
            $table->dateTime('last_read_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'thread_type', 'thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_message_reads');
    }
};
