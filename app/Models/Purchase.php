<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_id', 'product_id'];

    // No auto-incrementing id
    public $incrementing = false;

    // Disable timestamps since there's no updated_at
    public $timestamps = false;

    // If you want only created_at, alternatively use:
    // const CREATED_AT = 'created_at';
    // const UPDATED_AT = null;
}