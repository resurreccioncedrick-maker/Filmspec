<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders module: adds the pieces the mockup called for that the live table never had —
 * a real "Related To" link (booking/client/equipment/crew), a real "Assigned To" user relation
 * (person_in_charge stays as-is for existing rows and as a plain-text fallback for people who
 * aren't system users, e.g. an external partner), a Due Time for to-dos, a structured
 * multi-level Visibility selector on top of the existing visible_to_crew flag it keeps in sync
 * with, a "Low" priority option, and a real attendees list for meetings instead of tracking
 * only one meeting at a time with no named participants.
 */
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
