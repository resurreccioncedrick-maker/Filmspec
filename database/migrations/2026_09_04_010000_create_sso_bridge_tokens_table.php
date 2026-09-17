<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bridges a Laravel session to the legacy app's session (Part: "View CE" bug fix).
     *
     * filmspec.local (Laravel) and localhost/filmspec (legacy) are different hostnames, so
     * their filmspec_session cookies are entirely separate — a browser never sends the
     * Laravel-side cookie to the legacy vhost. Every redirect from Laravel into a
     * not-yet-ported legacy page (starting with ce_preview.php) was silently bouncing the
     * user to the legacy app's own login, because the legacy app saw no session at all.
     *
     * This table backs a short-lived, single-use, hashed token minted by Laravel
     * (App\Support\LegacyBridge::mint()) and consumed by the legacy app
     * (core/sso_bridge.php's consumeSsoBridgeToken()) to populate its session with the same
     * user, mirroring exactly what verify_mfa.php sets on a normal legacy login.
     */
    public function up(): void
    {
        Schema::create('sso_bridge_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token_hash', 64)->unique();
            $table->integer('user_id');
            $table->dateTime('expires_at');
            $table->boolean('used')->default(false);
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_bridge_tokens');
    }
};
