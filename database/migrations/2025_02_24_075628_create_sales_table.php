<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('sales');
        Schema::create('sales', function (Blueprint $table) {
            $table->id();                          // Auto-incrementing primary key
            $table->unsignedBigInteger('transaction_id'); // Links to transaction_sales.id
            $table->integer('product_id');         // Product ID
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};