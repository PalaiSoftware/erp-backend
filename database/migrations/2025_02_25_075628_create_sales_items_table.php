<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('sales_items', function (Blueprint $table) {
            $table->id();
            $table->integer('sale_id')->notNullable();
            $table->integer('quantity')->notNullable()->check('quantity > 0');
            $table->decimal('discount', 10, 2)->default(0)->check('discount >= 0');
            $table->decimal('per_item_cost', 10, 2)->notNullable()->check('per_item_cost >= 0');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_items');
    }
};
