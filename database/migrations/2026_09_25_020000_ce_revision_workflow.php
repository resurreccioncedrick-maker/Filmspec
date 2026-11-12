<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cost_estimates MODIFY COLUMN status ENUM('draft','issued','confirmed','superseded') NOT NULL DEFAULT 'draft'");

        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->text('confirmation_note')->nullable()->after('confirmed_at');
            $table->dateTime('superseded_at')->nullable()->after('confirmation_note');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('cost_approved_at')->nullable()->after('cost_approval_status');
        });

        DB::statement(
            "UPDATE cost_estimates ce
             JOIN (SELECT booking_id, MAX(ce_id) AS latest_id FROM cost_estimates WHERE status = 'confirmed' GROUP BY booking_id) latest
               ON latest.booking_id = ce.booking_id
             SET ce.status = 'superseded', ce.superseded_at = ce.confirmed_at
             WHERE ce.status = 'confirmed' AND ce.ce_id <> latest.latest_id"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE cost_estimates SET status = 'confirmed' WHERE status = 'superseded'");
        DB::statement("UPDATE cost_estimates SET status = 'draft' WHERE status = 'issued'");

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cost_approved_at');
        });

        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->dropColumn(['confirmation_note', 'superseded_at']);
        });

        DB::statement("ALTER TABLE cost_estimates MODIFY COLUMN status ENUM('draft','submitted','approved','revised','confirmed') DEFAULT 'draft'");
    }
};
