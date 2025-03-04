<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Create a sequence for sale_id
        DB::statement('CREATE SEQUENCE sale_id_seq START WITH 1 INCREMENT BY 1');
    }

    public function down()
    {
        // Drop the sequence
        DB::statement('DROP SEQUENCE sale_id_seq');
    }
};