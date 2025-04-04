<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    // Specify the table name (optional if it matches the model name pluralized)
    protected $table = 'categories';

    // Disable timestamps
    public $timestamps = false;

    // Define fillable fields for mass assignment
    protected $fillable = ['name'];
}