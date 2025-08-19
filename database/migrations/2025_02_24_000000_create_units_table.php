<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Use Laravel's ->id() — it creates primary key + sequence automatically
        Schema::create('units', function (Blueprint $table) {
            $table->id();                    // This creates: id (BIGINT) PRIMARY KEY, auto-increment
            $table->string('name', 50)->nullable(false);
        });

        // Step 2: PostgreSQL creates sequence: units_id_seq (starts at 1 by default)
        // We need to ALTER it to allow RESTART FROM 0
        DB::statement('ALTER SEQUENCE units_id_seq MINVALUE 0;');

        // Step 3: Now reset sequence to 0
        DB::statement('ALTER SEQUENCE units_id_seq RESTART WITH 0;');

        // Step 4: Insert data with explicit IDs: 0, 1, 2...
        $unitNames = [
            'None',
            'Box',
            'Piece',
            'Bundle',
            'Kg',
            'Liter',
            'Packet',
            'Carton',
            'Dozen',
            'Metre',
        ];

        foreach ($unitNames as $index => $name) {
            DB::table('units')->insert([
                'id' => $index,
                'name' => $name,
            ]);
        }

        // Step 5: Set next auto-increment value (e.g., 9)
        $nextId = count($unitNames); // 9
        DB::statement("ALTER SEQUENCE units_id_seq RESTART WITH {$nextId};");
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};