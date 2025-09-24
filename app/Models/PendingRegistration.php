<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'country',
        'password',
        'rid',
        'client_name',
        'client_address',
        'client_phone',
        'gst_no',
        'pan',
        'approved',
    ];

    protected $hidden = [
        'password',
    ];
}
