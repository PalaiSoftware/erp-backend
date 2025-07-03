<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
           // $table->integer('uid');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('gst_no')->nullable();
            $table->string('pan', 20)->nullable();
            $table->integer('blocked')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};

