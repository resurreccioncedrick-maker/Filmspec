<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->enum('pricing_mode', ['no_discount', 'package_price', 'discount_percent', 'discount_flat'])
                ->default('no_discount')->after('status');
            $table->decimal('pricing_input', 12, 2)->nullable()->after('pricing_mode');
            $table->boolean('vat_exempt')->default(false)->after('pricing_input');
        });
    }

    public function down(): void
    {
        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'pricing_input', 'vat_exempt']);
        });
    }
};
