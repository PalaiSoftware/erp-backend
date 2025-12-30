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
        Schema::table('sales_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_type_id')->nullable()->after('pan');

            // Foreign key: if customer type is deleted, set this field to null
            $table->foreign('customer_type_id')
                  ->references('id')
                  ->on('customer_types')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_clients', function (Blueprint $table) {
            // First drop the foreign key
            $table->dropForeign(['customer_type_id']);
            // Then drop the column
            $table->dropColumn('customer_type_id');
        });
    }
};