<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('ce_due_date')->nullable()->after('shoot_date_end');
            $table->enum('ce_type', ['fs_front', 'client_direct', 'partner_front'])->default('fs_front')->after('project_type');
            $table->string('ce_director_dop', 150)->nullable()->after('project_title');
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
