<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blue_book_entries', function (Blueprint $table) {
            $table->increments('entry_id');
            $table->enum('category', ['equipment', 'crew', 'outsourced', 'other'])->default('equipment');
            $table->string('item_name', 200);
            $table->decimal('rate', 10, 2);
            $table->enum('rate_unit', ['per_day', 'per_hour', 'per_project', 'flat'])->default('per_day');
            $table->string('description', 255)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blue_book_entries');
    }
};
