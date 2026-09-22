<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // No payment-deletion capability existed anywhere before this — this is a genuinely
            // new Void/Reversal action, not a replacement of one. Append-only by design: the
            // original row is never mutated or deleted, just flagged; the actual balance
            // correction happens via a separate negative-amount reversing row (reversal_of_id)
            // so every existing SUM(payments.amount) call site nets out correctly for free,
            // with no need to add "WHERE is_voided = 0" everywhere that already sums payments.
            // users.user_id and payments.payment_id are both plain SIGNED `int` in this schema
            // (matching payments.received_by's existing FK column) — integer(), not
            // unsignedInteger()/unsignedBigInteger(), or the FK add fails on a type/signedness
            // mismatch ("Referencing column and referenced column are incompatible").
            $table->boolean('is_voided')->default(false)->after('notes');
            $table->integer('voided_by')->nullable()->after('is_voided');
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->integer('reversal_of_id')->nullable()->after('void_reason');

            $table->foreign('voided_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('reversal_of_id')->references('payment_id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn(['is_voided', 'voided_by', 'voided_at', 'void_reason', 'reversal_of_id']);
        });
    }
};
