<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('mobile');
            $table->string('country');
            $table->string('password');
            $table->integer('rid'); // Role ID (not a foreign key)
            $table->integer('cid')->nullable();
            $table->integer('blocked')->default(0); // 0 = Active, 1 = Blocked
            $table->timestamps(); // This adds created_at & updated_at columns
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
