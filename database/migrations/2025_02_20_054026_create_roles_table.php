<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique();
        });

        // Insert predefined roles
        DB::table('roles')->insert([
            ['role' => 'Admin'],
            ['role' => 'Superuser'],
            ['role' => 'Moderator'],
            ['role' => 'Authenticated'],
            ['role' => 'Anonymous'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('roles');
    }
};
