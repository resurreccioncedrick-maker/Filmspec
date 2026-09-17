<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents (Part 18) — contracts, IDs, permits. Deliberately NOT the same pattern as
     * ImageUpload (public webroot, no auth check): these files can carry PII, so they're
     * stored on Laravel's private local disk and only ever reachable through the
     * access-controlled download route in DocumentController.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->increments('document_id');
            // Exactly one of these two is set — enforced in the controller, not the schema,
            // since "exactly one of two nullable FKs" isn't expressible as a DB constraint
            // without a CHECK clause this app's MySQL version may not support.
            $table->integer('booking_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->enum('category', ['contract', 'id', 'permit', 'other'])->default('other');
            $table->string('original_name', 255);
            // Random on-disk filename — never the original — so a leaked URL/path can't be
            // guessed from a predictable name.
            $table->string('stored_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('note', 255)->nullable();
            $table->integer('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('cascade');
            $table->foreign('client_id')->references('client_id')->on('clients')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index('booking_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
