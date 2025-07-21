<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGstToPurchaseItemsTable extends Migration
{
    public function up()
    {
        // Check if the column doesn't exist before adding it
        if (!Schema::hasColumn('purchase_items', 'gst')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->decimal('gst', 5, 2)->default(0.00)->after('dis');
            });
        }
    }

    public function down()
    {
        // Check if the column exists before dropping it
        if (Schema::hasColumn('purchase_items', 'gst')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropColumn('gst');
            });
        }
    }
}