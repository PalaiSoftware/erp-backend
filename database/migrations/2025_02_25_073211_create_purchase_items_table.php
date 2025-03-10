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
            $table->decimal('per_item_cost', 10, 2)->check('per_item_cost >= 0');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
}