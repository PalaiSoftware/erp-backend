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
            $table->integer('payment_mode');       // Payment mode as integer
            $table->decimal('absolute_discount', 12, 2)->default(0.00); // New field
            $table->decimal('total_paid', 12, 2)->default(0.00);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_sales');
    }
};