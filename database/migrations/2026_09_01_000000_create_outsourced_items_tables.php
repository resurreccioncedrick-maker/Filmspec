<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outsourced_packages', function (Blueprint $table) {
            $table->increments('package_id');
            $table->integer('booking_id');
            $table->string('package_name', 150);
            $table->string('supplier_name', 150)->nullable();
            $table->decimal('package_price', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('cascade');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index('booking_id');
        });

        Schema::create('outsourced_items', function (Blueprint $table) {
            $table->increments('item_id');
            $table->integer('booking_id');
            $table->unsignedInteger('package_id')->nullable();
            $table->string('item_name', 150);
            $table->string('category', 80)->nullable();
            $table->string('supplier_name', 150)->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('days')->default(1);
            $table->decimal('rate_per_day', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('cascade');
            $table->foreign('package_id')->references('package_id')->on('outsourced_packages')->onDelete('cascade');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
            $table->index('booking_id');
        });

        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->decimal('outsourced_total', 12, 2)->default(0)->after('accessories_total');
        });
    }

    public function down(): void
    {
        Schema::table('cost_estimates', function (Blueprint $table) {
            $table->dropColumn('outsourced_total');
        });
        Schema::dropIfExists('outsourced_items');
        Schema::dropIfExists('outsourced_packages');
    }
};
