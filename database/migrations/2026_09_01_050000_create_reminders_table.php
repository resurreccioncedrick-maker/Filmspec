<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->increments('reminder_id');
            $table->enum('type', ['todo', 'partners_meeting'])->default('todo');
            $table->string('title', 200);
            $table->date('reminder_date');
            $table->time('meeting_time')->nullable();
            $table->date('target_date')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('person_in_charge', 150)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_done')->default(false);
            $table->dateTime('done_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
