<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUidCidTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('uid_cid_table');
        Schema::create('uid_cid_table', function (Blueprint $table) {
            $table->bigInteger('uid')->unique();
            $table->bigInteger('cid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('uid_cid_table');
    }
}