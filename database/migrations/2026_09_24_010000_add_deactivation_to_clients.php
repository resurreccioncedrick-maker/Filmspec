<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Genuinely new — there's no active/inactive concept on clients today, only the
        // pending/approved/rejected portal-signup gate. Deactivation is a separate, reversible
        // state: a deactivated client keeps all historical bookings/invoices/payments but can't
        // be picked for a new booking (see BookingsController::addBooking).
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('status');
            $table->text('deactivation_reason')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('deactivation_reason');
            $table->integer('deactivated_by')->nullable()->after('deactivated_at');

            $table->foreign('deactivated_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by']);
            $table->dropColumn(['is_active', 'deactivation_reason', 'deactivated_at', 'deactivated_by']);
        });
    }
};
