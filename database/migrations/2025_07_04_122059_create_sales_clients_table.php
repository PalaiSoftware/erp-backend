<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('gst_no')->nullable();
            $table->string('pan', 20)->nullable();
            $table->integer('uid');
            $table->integer('cid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_clients');
    }
};