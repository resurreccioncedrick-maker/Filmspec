<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reminders MODIFY priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal'");

        Schema::table('reminders', function (Blueprint $table) {
            $table->string('related_type', 20)->nullable()->after('type');
            $table->integer('related_id')->nullable()->after('related_type');
            $table->integer('assigned_to')->nullable()->after('person_in_charge');
            $table->foreign('assigned_to')->references('user_id')->on('users')->nullOnDelete();
            $table->time('due_time')->nullable()->after('reminder_date');
            $table->string('visibility', 20)->nullable()->default('internal')->after('visible_to_crew');
            $table->string('visible_roles', 255)->nullable()->after('visibility');
        });

        Schema::create('reminder_attendees', function (Blueprint $table) {
            $table->id('attendee_id');
            $table->unsignedInteger('reminder_id');
            $table->foreign('reminder_id')->references('reminder_id')->on('reminders')->cascadeOnDelete();
            $table->string('name', 150);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_attendees');

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['related_type', 'related_id', 'assigned_to', 'due_time', 'visibility', 'visible_roles']);
        });

        DB::statement("ALTER TABLE reminders MODIFY priority ENUM('normal','high') NOT NULL DEFAULT 'normal'");
    }
};
