<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionPurchasesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');       // Purchase record identifier (replaces sale_id)
            $table->unsignedBigInteger('uid');                // User ID (creator)
            $table->unsignedBigInteger('cid')->nullable();    // Company ID, nullable if not always set
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
        Schema::dropIfExists('transaction_purchases');
    }
}
