<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Add 'cid' as nullable unsigned big integer
            $table->unsignedBigInteger('cid')->nullable()->after('c_factor');
            
            // Add 'uid' as nullable unsigned big integer
            $table->unsignedBigInteger('uid')->nullable()->after('cid');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cid', 'uid']);
        });
    }
};