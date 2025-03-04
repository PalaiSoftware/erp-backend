<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
         'name', 'address', 'phone', 'gst_no', 'pan', 'cuid','uid',
    ];

    public $timestamps = false; // Since we are not using created_at & updated_at
}
