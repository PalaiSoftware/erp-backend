<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGstToSalesItemsTable extends Migration
{
    public function up()
    {
        // Check if the 'gst' column doesn’t already exist
        if (!Schema::hasColumn('sales_items', 'gst')) {
            Schema::table('sales_items', function (Blueprint $table) {
                $table->decimal('gst', 5, 2)->default(0.00)->after('dis');
            });
        }
    }

    public function down()
    {
        // Check if the 'gst' column exists before dropping it
        if (Schema::hasColumn('sales_items', 'gst')) {
            Schema::table('sales_items', function (Blueprint $table) {
                $table->dropColumn('gst');
            });
        }
    }
};