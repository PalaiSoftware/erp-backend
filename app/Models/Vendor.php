<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;
    protected $fillable = [
        'vendor_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'gst_no',
        'pan',
        'uid',
        'cids',
    ];

    protected $casts = [
        'cids' => 'array',
    ];
}