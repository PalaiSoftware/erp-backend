<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('category_id')->default(0);
            $table->string('hscode')->nullable();
            $table->unsignedBigInteger('p_unit');
            $table->unsignedBigInteger('s_unit')->default(0);
            $table->decimal('c_factor', 22, 3)->default(0);
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
