<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->unsignedInteger('order_index')->default(0)->after('unit_id');
            $table->index(['bid', 'order_index']); // Composite index for fast sorting per bill
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->dropIndex(['bid', 'order_index']); // Drop index first
            $table->dropColumn('order_index');
        });
    }
};