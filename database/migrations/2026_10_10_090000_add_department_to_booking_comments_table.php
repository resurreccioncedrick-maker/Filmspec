<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Routes a booking's client chat into separate department threads (Staff / Accounting /
        // Operations Manager) instead of one flat thread. Nullable — existing rows predate this
        // and are treated as the 'staff' bucket by the app (not backfilled here, since "staff"
        // is already the correct read of a plain NULL rather than a real historical fact to set).
        Schema::table('booking_comments', function (Blueprint $table) {
            $table->string('department', 30)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_comments', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
