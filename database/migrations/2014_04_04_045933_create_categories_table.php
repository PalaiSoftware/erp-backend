<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        // Create the categories table
        Schema::create('categories', function (Blueprint $table) {
            $table->id();              // Primary key: auto-incrementing ID
            $table->string('name');    // Name field as a string
            // No timestamps or blocked field
        });

        // Add basic categories, including "Hardware"
        $basicCategories = [
            ['name' => 'Hardware'],
            ['name' => 'Electronics'],
            ['name' => 'Clothing'],
            ['name' => 'Books'],
            ['name' => 'Furniture']
        ];

        foreach ($basicCategories as $category) {
            Category::create($category);
        }
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
}