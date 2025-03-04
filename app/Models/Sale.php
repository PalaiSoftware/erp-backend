<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = ['sale_id', 'product_id'];

    // Tell Laravel there’s no auto-incrementing id
    public $incrementing = false;

    // Tell Laravel there’s no primary key (or set it to sale_id if you want)
    protected $primaryKey = null; // Or 'sale_id' if you want to use it
}