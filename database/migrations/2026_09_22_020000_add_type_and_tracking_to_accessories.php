<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accessories', function (Blueprint $table) {
            // accessory_type is a richer 3-way classification than the existing boolean
            // is_included — kept in sync with is_included (package_inclusion <=> is_included=1)
            // rather than replacing it, so every existing is_included read/write elsewhere in
            // the app keeps working unchanged.
            $table->enum('accessory_type', ['package_inclusion', 'optional_addon', 'internal_operational'])
                ->default('optional_addon')->after('is_included');
            // Existing accessories all stay 'quantity' (matches how they're tracked today);
            // 'individual' is opt-in per accessory going forward via accessory_units.
            $table->enum('tracking_method', ['quantity', 'individual'])->default('quantity')->after('accessory_type');
        });

        Schema::table('equipment_accessory_links', function (Blueprint $table) {
            $table->unsignedInteger('included_qty')->nullable()->after('accessory_id');
        });

        // Backfill: is_included=1 -> package_inclusion, else optional_addon (internal_operational
        // has no prior equivalent and is only chosen going forward via the edit form).
        DB::table('accessories')->where('is_included', 1)->update(['accessory_type' => 'package_inclusion']);
        DB::table('accessories')->where('is_included', 0)->update(['accessory_type' => 'optional_addon']);
    }

    public function down(): void
    {
        Schema::table('equipment_accessory_links', function (Blueprint $table) {
            $table->dropColumn('included_qty');
        });
        Schema::table('accessories', function (Blueprint $table) {
            $table->dropColumn(['accessory_type', 'tracking_method']);
        });
    }
};
