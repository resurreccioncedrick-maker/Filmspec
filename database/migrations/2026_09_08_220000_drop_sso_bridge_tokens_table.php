<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The SSO bridge (App\Support\LegacyBridge / core/sso_bridge.php) existed only to carry a
     * logged-in Laravel user's identity across into not-yet-ported legacy pages. Every one of
     * those redirects has since been replaced with a native Laravel render (CE preview,
     * checklist print) or a same-app link (billing, attendance, cost-email), so nothing mints
     * or consumes a bridge token anymore — this table is dead.
     */
    public function up(): void
    {
        Schema::dropIfExists('sso_bridge_tokens');
    }

    public function down(): void
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
};
