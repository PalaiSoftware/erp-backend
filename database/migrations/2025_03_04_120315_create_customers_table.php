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
            $table->integer('cid');                 // Single company ID (required, integer)
            $table->string('first_name');           // First name (required)
            $table->string('last_name')->nullable(); // Last name (optional)
            $table->string('email')->nullable();    // Email (optional)
            $table->string('phone', 20)->nullable(); // Phone number (optional)
            $table->string('gst')->nullable();      // GST number (optional)
            $table->string('pan')->nullable();      // PAN number (optional)
            $table->text('address')->nullable();    // Address (optional)
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