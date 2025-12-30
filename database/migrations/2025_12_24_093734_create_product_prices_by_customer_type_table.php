<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('product_prices_by_type', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('customer_type_id');
        $table->unsignedBigInteger('cid');
        $table->decimal('selling_price', 12, 2); // pre-GST selling price
        $table->timestamps();

        $table->unique(['product_id', 'customer_type_id', 'cid']);
        $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        $table->foreign('customer_type_id')->references('id')->on('customer_types')->onDelete('cascade');
        $table->foreign('cid')->references('id')->on('clients')->onDelete('cascade');
    });
}
    public function down(): void
    {
        Schema::dropIfExists('product_prices_by_customer_type');
    }
};
