<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'cid',
        'created_by',
        'created_by_rid',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Client::class, 'cid');
    }
}
