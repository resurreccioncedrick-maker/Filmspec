<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Default 'approved' so existing rows and staff-created clients (via the admin
            // "Add Client" form) are never blocked — only the self-service signup flow
            // (AuthController::verifySignup()) explicitly sets 'pending'.
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('client_type');
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_reason']);
        });
    }
};
