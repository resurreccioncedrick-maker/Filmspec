<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_purchase_tickets', function (Blueprint $table) {
            // Which physical unit this is about — distinct from the catalog item, since you
            // can own several of the same model.
            $table->string('serial_no', 100)->nullable()->after('equipment_id');
            // Who flagged it, as opposed to person_in_charge, who is handling it.
            $table->string('reported_by', 150)->nullable()->after('person_in_charge');
        });

        // Running thread per ticket. Same shape as booking_comments, which is the app's
        // existing comment pattern.
        Schema::create('repair_purchase_messages', function (Blueprint $table) {
            $table->increments('message_id');
            $table->integer('ticket_id')->unsigned();
            $table->integer('user_id')->nullable();
            $table->text('body');
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('ticket_id')->references('ticket_id')->on('repair_purchase_tickets')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_purchase_messages');
        Schema::table('repair_purchase_tickets', function (Blueprint $table) {
            $table->dropColumn(['serial_no', 'reported_by']);
        });
    }
};
