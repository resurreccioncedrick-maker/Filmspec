<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
