<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Genuinely new — nothing in the app currently tracks crew certifications/licenses.
        // Backs the Crew Management detail panel's Qualifications tab; the History tab needs no
        // new table since activity_logs already records every crew action with module='crew' /
        // record_id=crew_id (CrewController::handleAction() has been writing these all along).
        Schema::create('crew_qualifications', function (Blueprint $table) {
            $table->id('qualification_id');
            $table->integer('crew_id');
            $table->string('title', 150);
            $table->string('issuing_body', 150)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('crew_id')->references('crew_id')->on('crew_members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_qualifications');
    }
};
