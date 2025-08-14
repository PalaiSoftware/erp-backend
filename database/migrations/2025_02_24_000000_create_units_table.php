<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create the units table
        Schema::create('units', function (Blueprint $table) {
            $table->id();                    // Auto-incrementing primary key
            $table->string('name', 50)->nullable(false); // Name field, max 50 chars, not nullable
           
        });

        // Insert sample data
        DB::table('units')->insert([
            ['name' => 'Box'],
            ['name' => 'Piece'],
            ['name' => 'Bundle'],
            ['name' => 'Kg'],
            ['name' => 'Liter'],
            ['name' => 'Packet,'],
            ['name' => 'Carton,'],
            ['name' => 'Dozen,'],
            ['name' => 'Metre,'],

        ]);
    }

    public function down(): void
    {
        // Drop the units table if rolling back
        Schema::dropIfExists('units');
    }
};