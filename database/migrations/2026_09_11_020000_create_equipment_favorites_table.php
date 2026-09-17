<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_favorites', function (Blueprint $table) {
            $table->increments('favorite_id');
            $table->integer('user_id');
            $table->integer('equipment_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_favorites');
    }
};
