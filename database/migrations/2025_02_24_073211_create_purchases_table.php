<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchasesTable extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('transaction_id'); // Links to transaction_purchases.id
            $table->unsignedBigInteger('product_id'); // Product ID
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('transaction_id')->references('id')->on('transaction_purchases')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
}