<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSerialNumbersToSalesItemsTable extends Migration
{
    public function up()
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->text('serial_numbers')->nullable()->after('gst');
        });
    }

    public function down()
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });
    }
}