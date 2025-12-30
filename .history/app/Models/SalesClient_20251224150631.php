<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesClient extends Model
{
    protected $table = 'sales_clients';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'gst_no',
        'pan',
        'uid',
        'cid',
        'customer_type_id',
    ];

    // Add relationships or methods here if needed
    public function customerType()
{
    return $this->belongsTo(\App\Models\CustomerType::class, 'customer_type_id');
}
}