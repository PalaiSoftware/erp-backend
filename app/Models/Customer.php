<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    // Optional: specify the table name if not following Laravel conventions
    protected $table = 'customers';

   // Mass assignable attributes
   protected $fillable = [
    'cid',
    'first_name',
    'last_name',
    'email',
    'phone',
    'gst',
    'pan',
    'address'
];

// Casts for type conversion
protected $casts = [
    'cid' => 'integer',
];

    public $timestamps = false;
}
