<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    // Optional: specify the table name if not following Laravel conventions
    protected $table = 'customers';

    // Mass assignable attributes
    protected $fillable = [
        'cid',
        'name',
        'email',
        'phone',
        'address'
    ];
    public $timestamps = false;
}
