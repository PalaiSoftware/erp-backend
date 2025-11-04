<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('incremental_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('bid');
            $table->date('date');
            $table->decimal('amount', 12, 2); 

            $table->foreign('bid')->references('id')->on('sales_bills')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('incremental_payments');
    }
};
