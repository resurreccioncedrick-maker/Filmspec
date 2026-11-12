<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('outsourced_items');
        Schema::dropIfExists('outsourced_packages');
        Schema::dropIfExists('outsourced_presets');
    }

    public function down(): void
    {
    }
};
