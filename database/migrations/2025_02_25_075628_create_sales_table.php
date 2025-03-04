<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Drop the existing sales table
        Schema::dropIfExists('sales');

        // Recreate with sale_id as a manual bigint
        Schema::create('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id'); // Manual sale_id, not auto-incrementing
            $table->integer('product_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};