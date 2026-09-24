<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_checklist', function (Blueprint $table) {
            $table->integer('equipment_id')->nullable()->change();
            $table->integer('accessory_id')->nullable()->after('equipment_id');
            $table->foreign('accessory_id')->references('accessory_id')->on('accessories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_checklist', function (Blueprint $table) {
            $table->dropForeign(['accessory_id']);
            $table->dropColumn('accessory_id');
            $table->integer('equipment_id')->nullable(false)->change();
        });
    }
};
