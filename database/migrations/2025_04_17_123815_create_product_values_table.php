<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('product_values', function (Blueprint $table) {
            $table->unsignedBigInteger('pid')->primary(); // Product ID as primary key
            $table->decimal('sale_discount_percent', 5, 2)->default(0);
            $table->decimal('sale_discount_flat', 8, 2)->default(0);
            $table->decimal('selling_price', 8, 2)->default(0);
            $table->timestamps(); // Optional: Adds created_at/updated_at
            
            // Foreign key to products table
            $table->foreign('pid')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_values');
    }
};