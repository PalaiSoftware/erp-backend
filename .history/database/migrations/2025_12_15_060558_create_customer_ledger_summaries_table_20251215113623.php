<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledger_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id'); // sales_clients.id
            $table->unsignedBigInteger('cid');         // company id
            $table->decimal('total_purchase', 16, 2)->default(0);
            $table->decimal('total_paid', 16, 2)->default(0);
            $table->decimal('total_due', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['customer_id', 'cid']);
            $table->index('cid');
            $table->index('total_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_summaries');
    }
};