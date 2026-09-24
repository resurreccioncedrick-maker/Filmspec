<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accessories', function (Blueprint $table) {
            $table->boolean('is_active')->default(1)->after('quantity_in_use');
            // Governs whether an Optional Add-On is offered as a selectable item in the public
            // equipment catalog — Package Inclusion / Internal-Operational accessories aren't
            // standalone catalog products, so this only applies in practice to add-ons.
            $table->boolean('is_public')->default(1)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('accessories', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_public']);
        });
    }
};
