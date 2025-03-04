<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->integer('purchase_id'); // No foreign key
            $table->integer('vendor_id');   // No foreign key
            $table->integer('quantity')->check('quantity > 0');
            $table->decimal('per_item_cost', 10, 2)->check('per_item_cost >= 0');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_items');
    }
};
