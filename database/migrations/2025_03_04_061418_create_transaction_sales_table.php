<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_sales', function (Blueprint $table) {
            $table->id();                          // Auto-incrementing primary key
            $table->unsignedBigInteger('uid');     // User ID (creator)
            $table->unsignedBigInteger('cid')->nullable(); // Company ID, nullable
            $table->unsignedBigInteger('customer_id'); // Customer ID
            $table->string('payment_mode', 50);    // Payment mode
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_sales');
    }
};