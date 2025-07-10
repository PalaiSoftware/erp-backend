<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales_bills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('bill_name')->nullable();
            $table->bigInteger('scid');
            $table->bigInteger('uid');
            $table->integer('payment_mode');
            $table->decimal('absolute_discount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_bills');
    }
};
