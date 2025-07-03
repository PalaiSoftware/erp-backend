<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
         'name', 'address', 'phone', 'gst_no', 'pan', 'blocked',
    ];

    public $timestamps = false; // Since we are not using created_at & updated_at
}

