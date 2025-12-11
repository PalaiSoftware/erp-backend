<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('bill_id');
            $table->decimal('paid_amount', 14, 2);
            $table->date('paid_on');
            $table->string('payment_mode')->default('Cash');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('sales_clients')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('sales_bills')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['customer_id', 'bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_bill_payments');
    }
};