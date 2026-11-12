<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
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
