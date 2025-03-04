<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_name'); // Vendor name
            $table->string('contact_person')->nullable(); // Contact person
            $table->string('email')->unique()->nullable(); // Email (unique)
            $table->string('phone', 20)->nullable(); // Phone number
            $table->text('address')->nullable(); // Address
            $table->string('gst_no')->nullable(); // GST Number
            $table->string('pan', 20)->nullable(); // PAN Number
            $table->integer('uid');
            $table->timestamp('created_at')->useCurrent(); // Auto-filled timestamp
        });
    }

    public function down()
    {
        Schema::dropIfExists('vendors');
    }
};
