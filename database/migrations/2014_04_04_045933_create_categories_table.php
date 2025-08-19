<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        // Step 1: Create the table WITHOUT auto-incrementing ID initially
        Schema::create('categories', function (Blueprint $table) {
            $table->integer('id');   // Use integer, not $table->id()
            $table->string('name');
            $table->primary('id');   // Set id as primary key
        });

        // Step 2: Create a sequence that allows 0 as min value
        DB::statement('CREATE SEQUENCE IF NOT EXISTS categories_id_seq MINVALUE 0 START WITH 0;');

        // Step 3: Link the sequence to the id column
        DB::statement('ALTER TABLE categories ALTER COLUMN id SET DEFAULT nextval(\'categories_id_seq\');');

        // Step 4: Insert categories with explicit IDs: 0, 1, 2, 3...
        $categoryNames = [
            'None',
            'Hardware & Tools',
            'Electronics',
            'Furniture',
            'Mobile & Accessories',
            'Laptops & Computers',
            'Fashion',
            'Books',
            'Sports & Fitness',
        ];

        foreach ($categoryNames as $index => $name) {
            DB::table('categories')->insert([
                'id' => $index,
                'name' => $name,
            ]);
        }

        // Step 5: Update sequence to next available ID (e.g., 10)
        $nextId = count($categoryNames); // 10
        DB::statement("ALTER SEQUENCE categories_id_seq RESTART WITH {$nextId};");
    }

    public function down()
    {
        // Drop sequence first, then table
        DB::statement('DROP SEQUENCE IF EXISTS categories_id_seq CASCADE;');
        Schema::dropIfExists('categories');
    }
}