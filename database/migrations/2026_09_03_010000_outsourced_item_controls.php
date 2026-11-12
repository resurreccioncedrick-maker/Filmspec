<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsourced_items', function (Blueprint $table) {
            $table->boolean('special_packaged')->default(true)->after('rate_per_day');
            $table->boolean('no_discount')->default(false)->after('special_packaged');
            $table->integer('sort_order')->default(0)->after('no_discount');
        });

        DB::statement('UPDATE outsourced_items SET sort_order = item_id');
    }

    public function down(): void
    {
        Schema::table('outsourced_items', function (Blueprint $table) {
            $table->dropColumn(['special_packaged', 'no_discount', 'sort_order']);
        });
    }
};
