<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_support_messages', function (Blueprint $table) {
            $table->increments('message_id');
            $table->integer('client_id');
            $table->integer('user_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('client_id')->references('client_id')->on('clients')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_support_messages');
    }
};
