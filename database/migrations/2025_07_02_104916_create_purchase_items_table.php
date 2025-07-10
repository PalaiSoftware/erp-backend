<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseItemsTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->unsignedBigInteger('bid');
            $table->unsignedBigInteger('pid');
            $table->decimal('p_price', 12, 2);
            $table->decimal('s_price', 12, 2)->default(0);
            $table->decimal('quantity', 22, 3)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('dis', 12, 2)->default(0);

            $table->foreign('bid')->references('id')->on('purchase_bills')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_items');
    }
}