<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BIR Tax Identification Number, shown on the printed Invoice's "Bill To" block —
        // nullable since most existing clients won't have one on file yet.
        Schema::table('clients', function (Blueprint $table) {
            $table->string('tin', 30)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('tin');
        });
    }
};
