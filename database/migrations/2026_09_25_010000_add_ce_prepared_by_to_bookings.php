<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Was a hardcoded name baked into the CE document template — every booking's CE
        // printed the same person's name as "Prepared by" regardless of who actually
        // handled it. Matches the existing ce_* column convention on bookings (ce_due_date,
        // ce_director_dop, ce_contact_person, ...), edited from the same modal.
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('ce_prepared_by', 150)->nullable()->after('ce_contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('ce_prepared_by');
        });
    }
};
