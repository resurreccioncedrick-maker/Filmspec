<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CE project details (Part 7).
     *
     * Every column is `ce_`-prefixed on purpose. Both apps are full of
     * `SELECT b.*, c.contact_person, ...` joins against `clients` (and SOA/invoice joins that
     * carry their own `due_date`); an unprefixed `contact_person` or `due_date` on `bookings`
     * would land twice in the same result set, PHP would keep whichever came last, and this
     * override would be silently invisible — a bug that depends on column order.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // NULL ce_due_date IS the reference tool's "None" checkbox.
            $table->date('ce_due_date')->nullable()->after('shoot_date_end');
            $table->enum('ce_type', ['fs_front', 'client_direct', 'partner_front'])->default('fs_front')->after('project_type');
            $table->string('ce_director_dop', 150)->nullable()->after('project_title');
            // Per-booking overrides — blank falls back to the client record when the CE prints.
            $table->string('ce_contact_person', 150)->nullable()->after('ce_director_dop');
            $table->string('ce_contact_number', 50)->nullable()->after('ce_contact_person');
            $table->string('ce_contact_email', 150)->nullable()->after('ce_contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['ce_due_date', 'ce_type', 'ce_director_dop', 'ce_contact_person', 'ce_contact_number', 'ce_contact_email']);
        });
    }
};
