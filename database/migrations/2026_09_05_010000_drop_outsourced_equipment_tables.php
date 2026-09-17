<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Outsourced/Partner Equipment feature entirely, at the user's explicit request.
 * All application code that read/wrote these tables has already been removed (booking detail
 * controller/views on both apps, CE documents, P&L, Equipment Data reporting). Confirmed empty
 * (0 rows in all three) before this migration was written — no data migration needed.
 *
 * Drop order matters: outsourced_items FKs to outsourced_packages, so items must go first.
 * cost_estimates.outsourced_total is NOT touched — it stays as a real column (other reporting
 * code still reads it), just always written as 0 going forward.
 */
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
        // Deliberate one-way removal — restore from a pre-migration backup if ever needed
        // rather than recreating empty tables here.
    }
};
