<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentModesTable extends Migration
{
    public function up()
    {
        Schema::create('payment_modes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

         // Insert sample data
         DB::table('payment_modes')->insert([
            ['name' => 'credit_card'],
            ['name' => 'debit_card'],
            ['name' => 'cash'],
            ['name' => 'upi'],
            ['name' => 'bank_transfer'],
            ['name' => 'online'],
            ['name' => 'phonepe'],

        ]);
    }

    public function down()
    {
        Schema::dropIfExists('payment_modes');
    }
}