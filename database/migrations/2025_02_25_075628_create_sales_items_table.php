<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id'); // Links to sales.id
            $table->decimal('quantity',22,3)->check('quantity >= 0');
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('flat_discount', 10, 2)->default(0);
            $table->decimal('per_item_cost', 22, 3);
            $table->unsignedBigInteger('unit_id'); // Links to units.id
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('unit_id')->references('id')->on('units');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_items');
    }
};