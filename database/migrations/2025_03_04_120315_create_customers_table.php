<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();                           // Auto-increment serial primary key
            $table->unsignedBigInteger('cid');        // Company ID
            $table->string('name');                   // Customer name
            $table->string('email')->nullable();      // Email (optional)
            $table->string('phone', 20);              // Phone number
            $table->text('address')->nullable();      // Address (optional)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
