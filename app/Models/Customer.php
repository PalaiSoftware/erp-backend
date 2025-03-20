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
        'cids',
        'name',
        'email',
        'phone',
        'address'
    ];
    protected $casts = [
        'cids' => 'array',
    ];

    public $timestamps = false;
}
