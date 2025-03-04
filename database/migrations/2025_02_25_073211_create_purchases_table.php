<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Drop the existing purchases table
        Schema::dropIfExists('purchases');

        // Recreate with purchase_id
        Schema::create('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id'); // Manual purchase_id, not auto-incrementing
            $table->integer('product_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchases');
    }
};