<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionPurchasesTable extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_purchases', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key (transaction_id)
            $table->unsignedBigInteger('uid'); // User ID
            $table->unsignedBigInteger('cid')->nullable(); // Company ID
            // $table->decimal('total_amount', 10, 2);
            $table->string('payment_mode', 50);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_purchases');
    }
}