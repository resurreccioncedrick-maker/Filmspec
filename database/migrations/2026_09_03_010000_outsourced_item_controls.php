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
            // Covered by the group's agreed package price. Defaults TRUE so every existing row
            // keeps behaving exactly as it does today (items in a priced package bill at 0) —
            // this flag makes that previously hard-coded rule overridable per item.
            $table->boolean('special_packaged')->default(true)->after('rate_per_day');
            // Billed at full rate, outside the discount — added on top of the packaged cost.
            $table->boolean('no_discount')->default(false)->after('special_packaged');
            $table->integer('sort_order')->default(0)->after('no_discount');
        });

        // Backfill so existing rows keep their current (item_id) order once lists switch to
        // ordering by sort_order.
        DB::statement('UPDATE outsourced_items SET sort_order = item_id');
    }

    public function down(): void
    {
        Schema::table('outsourced_items', function (Blueprint $table) {
            $table->dropColumn(['special_packaged', 'no_discount', 'sort_order']);
        });
    }
};
