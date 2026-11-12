<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            if (! Schema::hasColumn('bookings', 'field_arrival_confirmed_at')) {
                $table->timestamp('field_arrival_confirmed_at')->nullable()->after('transport_confirmed_at');
            }
            if (! Schema::hasColumn('bookings', 'field_arrival_confirmed_by')) {
                $table->integer('field_arrival_confirmed_by')->nullable()->after('field_arrival_confirmed_at');
            } else {
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
