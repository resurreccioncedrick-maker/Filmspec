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
            $table->enum('accessory_type', ['package_inclusion', 'optional_addon', 'internal_operational'])
                ->default('optional_addon')->after('is_included');
            $table->enum('tracking_method', ['quantity', 'individual'])->default('quantity')->after('accessory_type');
        });

        Schema::table('equipment_accessory_links', function (Blueprint $table) {
            $table->unsignedInteger('included_qty')->nullable()->after('accessory_id');
        });

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
