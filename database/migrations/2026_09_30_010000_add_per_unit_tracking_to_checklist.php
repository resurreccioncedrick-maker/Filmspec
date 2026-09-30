<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit checklist tracking. Today equipment_checklist has exactly one row per
 * (booking, equipment/accessory, direction) — a booking with quantity>1 of the same model has
 * no way to tell its units apart, so one damaged unit's condition silently overwrites/represents
 * all of them. This adds an optional unit identity to each checklist/incident row:
 * equipment_unit_id / accessory_unit_id for genuinely serialized items (equipment_units /
 * accessory_units), or unit_seq (1, 2, 3…) for a multi-quantity item that has no registered
 * physical units at all. A row with all three null is the pre-existing whole-line aggregate
 * behavior, kept for quantity=1 items.
 *
 * Every step is guarded with hasColumn/hasForeign-style checks so this migration can resume
 * cleanly if it ever partially applies (each ALTER TABLE auto-commits in MySQL — this isn't
 * transactional DDL) — that happened once in local development from a users.user_id type
 * mismatch below, before the fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_checklist', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment_checklist', 'equipment_unit_id')) {
                $table->unsignedBigInteger('equipment_unit_id')->nullable()->after('accessory_id');
            }
            if (! Schema::hasColumn('equipment_checklist', 'accessory_unit_id')) {
                $table->unsignedBigInteger('accessory_unit_id')->nullable()->after('equipment_unit_id');
            }
            if (! Schema::hasColumn('equipment_checklist', 'unit_seq')) {
                $table->unsignedTinyInteger('unit_seq')->nullable()->after('accessory_unit_id');
            }
        });
        if (! $this->hasForeign('equipment_checklist', 'equipment_checklist_equipment_unit_id_foreign')) {
            Schema::table('equipment_checklist', function (Blueprint $table) {
                $table->foreign('equipment_unit_id')->references('unit_id')->on('equipment_units')->nullOnDelete();
            });
        }
        if (! $this->hasForeign('equipment_checklist', 'equipment_checklist_accessory_unit_id_foreign')) {
            Schema::table('equipment_checklist', function (Blueprint $table) {
                $table->foreign('accessory_unit_id')->references('unit_id')->on('accessory_units')->nullOnDelete();
            });
        }

        Schema::table('incident_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('incident_reports', 'equipment_unit_id')) {
                $table->unsignedBigInteger('equipment_unit_id')->nullable()->after('accessory_id');
            }
            if (! Schema::hasColumn('incident_reports', 'accessory_unit_id')) {
                $table->unsignedBigInteger('accessory_unit_id')->nullable()->after('equipment_unit_id');
            }
        });
        if (! $this->hasForeign('incident_reports', 'incident_reports_equipment_unit_id_foreign')) {
            Schema::table('incident_reports', function (Blueprint $table) {
                $table->foreign('equipment_unit_id')->references('unit_id')->on('equipment_units')->nullOnDelete();
            });
        }
        if (! $this->hasForeign('incident_reports', 'incident_reports_accessory_unit_id_foreign')) {
            Schema::table('incident_reports', function (Blueprint $table) {
                $table->foreign('accessory_unit_id')->references('unit_id')->on('accessory_units')->nullOnDelete();
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            // Crew's own confirmation that released equipment actually arrived on field —
            // surfaced to office roles on the Checklist page, set only via the Crew Portal.
            if (! Schema::hasColumn('bookings', 'field_arrival_confirmed_at')) {
                $table->timestamp('field_arrival_confirmed_at')->nullable()->after('transport_confirmed_at');
            }
            if (! Schema::hasColumn('bookings', 'field_arrival_confirmed_by')) {
                // users.user_id is a plain (signed) int, not unsigned — matching that exactly,
                // the same way every other *_by/*_id FK onto users in this schema already does.
                $table->integer('field_arrival_confirmed_by')->nullable()->after('field_arrival_confirmed_at');
            } else {
                // A first attempt at this migration (before this signed/unsigned fix) may have
                // already created the column as unsignedBigInteger — correct it in place rather
                // than skip it, or the foreign key below fails again with the same type clash.
                $table->integer('field_arrival_confirmed_by')->nullable()->change();
            }
        });
        if (! $this->hasForeign('bookings', 'bookings_field_arrival_confirmed_by_foreign')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreign('field_arrival_confirmed_by')->references('user_id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['field_arrival_confirmed_by']);
            $table->dropColumn(['field_arrival_confirmed_at', 'field_arrival_confirmed_by']);
        });

        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropForeign(['equipment_unit_id']);
            $table->dropForeign(['accessory_unit_id']);
            $table->dropColumn(['equipment_unit_id', 'accessory_unit_id']);
        });

        Schema::table('equipment_checklist', function (Blueprint $table) {
            $table->dropForeign(['equipment_unit_id']);
            $table->dropForeign(['accessory_unit_id']);
            $table->dropColumn(['equipment_unit_id', 'accessory_unit_id', 'unit_seq']);
        });
    }

    private function hasForeign(string $table, string $constraintName): bool
    {
        $row = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraintName, 'FOREIGN KEY']
        );

        return (bool) $row;
    }
};
