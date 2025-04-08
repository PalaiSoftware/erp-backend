<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('purchase_id'); // Links to purchases.id
            $table->unsignedBigInteger('vendor_id');
            $table->integer('quantity')->check('quantity > 0');
            $table->decimal('per_item_cost', 22, 3)->check('per_item_cost >= 0');
            $table->decimal('discount', 10, 2)->default(0);
            $table->unsignedBigInteger('unit_id'); // Links to units.id
            $table->timestamp('created_at')->useCurrent();
            
            // Foreign key constraints
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
}