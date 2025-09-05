<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductInfoTable extends Migration
{
    public function up()
    {
        Schema::create('product_info', function (Blueprint $table) {
            $table->unsignedBigInteger('pid');
            $table->string('hsn_code')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('profit_percentage', 5, 2)->default(0);
            $table->decimal('pre_gst_sale_cost', 15, 2)->default(0);
            $table->decimal('gst', 5, 2)->default(0);
            $table->decimal('post_gst_sale_cost', 15, 2)->default(0);
            $table->unsignedBigInteger('uid');
            $table->unsignedBigInteger('cid');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_info');
    }
}