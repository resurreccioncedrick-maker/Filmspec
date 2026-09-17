<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the replacement index before dropping the old one — the FK on booking_id
        // needs a supporting index at all times, so MySQL refuses to drop the only one.
        DB::statement('ALTER TABLE cost_estimates ADD INDEX cost_estimates_booking_id_idx (booking_id)');
        DB::statement('ALTER TABLE cost_estimates DROP INDEX booking_id');
        DB::statement("ALTER TABLE cost_estimates MODIFY COLUMN status ENUM('draft','submitted','approved','revised','confirmed') DEFAULT 'draft'");

        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->integer('confirmed_by')->nullable()->after('generated_by');
            $table->dateTime('confirmed_at')->nullable()->after('confirmed_by');
            $table->foreign('confirmed_by')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['confirmed_by', 'confirmed_at']);
        });
        DB::statement("ALTER TABLE cost_estimates MODIFY COLUMN status ENUM('draft','submitted','approved','revised') DEFAULT 'draft'");
        // Same ordering requirement as up(): add the unique index back before dropping
        // the plain one that's currently backing the FK.
        DB::statement('ALTER TABLE cost_estimates ADD UNIQUE KEY booking_id (booking_id)');
        DB::statement('ALTER TABLE cost_estimates DROP INDEX cost_estimates_booking_id_idx');
    }
};
