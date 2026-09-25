<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panelist revision pass — CE Analytics correctness fixes:
 *  - 'issued' (Issued / Awaiting Confirmation) and 'superseded' become real, storable statuses
 *    instead of 'superseded' being computed on the fly at query time only.
 *  - confirmation_note lets staff record how/why a CE was confirmed (e.g. "client approved via
 *    email Sep 20") — the CE-level equivalent of a client-acceptance reference.
 *  - bookings.cost_approved_at is a stable timestamp for the client's own approve action
 *    (ClientBookingDetailController::act 'client_approve_cost'), distinct from the
 *    general-purpose updated_at column other unrelated writes also touch.
 *  - Backfill: any booking that already has 2+ 'confirmed' rows (the literal "15 vs 14" bug —
 *    BookingCosting::confirmCe() never used to demote the row it superseded) gets every row but
 *    the newest moved to 'superseded' here, so the fix is real at the data level, not just
 *    hidden behind a query filter.
 */
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

        // Backfill: demote every confirmed CE row except the newest, per booking.
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
