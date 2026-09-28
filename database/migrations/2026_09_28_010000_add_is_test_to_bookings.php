<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar Data Analytics was recommended to exclude "named test/demo bookings" from its
 * density/utilization figures, but the codebase had no way to tell a real booking from a
 * seeded demo one short of matching literal project-title strings (fragile, and the actual
 * demo titles don't contain any obvious "test"/"demo" marker). A real flag, set once at
 * seed time, is what makes an exclusion rule durable instead of a one-off manual cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
