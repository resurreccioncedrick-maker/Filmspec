<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outsourced_presets', function (Blueprint $table) {
            $table->increments('preset_id');
            $table->string('label', 100);
            $table->string('item_name', 150);
            $table->string('category', 80)->nullable();
            $table->string('supplier_name', 150)->nullable();
            $table->decimal('rate_per_day', 10, 2)->default(0);
            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outsourced_presets');
    }
};
