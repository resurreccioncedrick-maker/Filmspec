<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same move as 2026_10_10_090000_add_department_to_booking_comments_table — routes the
        // general support widget into separate department threads (Staff / Accounting / Traffic /
        // Operations Manager) instead of one flat "FilmSpec" thread. Nullable for the same reason:
        // existing rows predate this and are correctly read as the 'staff' bucket by the app, not
        // backfilled here.
        Schema::table('client_support_messages', function (Blueprint $table) {
            $table->string('department', 30)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_support_messages', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
