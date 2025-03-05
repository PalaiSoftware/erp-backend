<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionSalesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');       // Sale record identifier (not enforced as foreign key)
            $table->unsignedBigInteger('uid');             // User ID (creator)
            $table->unsignedBigInteger('cid')->nullable(); // Company ID, now nullable
            $table->unsignedBigInteger('customer_id');     // Customer ID
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_mode', 50);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_sales');
    }
}
