<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // FilmSpec already has its own `users` and `roles` tables (see
        // app/Models/User.php + app/Models/Role.php), so Laravel's default
        // users/password_reset_tokens migration is skipped to avoid conflicting
        // with the existing schema. The legacy schema also already has an
        // unrelated, empty `sessions` table, so Laravel's is created under
        // `laravel_sessions` (see config/session.php) instead of colliding.
        $table = config('session.table', 'sessions');

        Schema::create($table, function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('session.table', 'sessions'));
    }
};
