<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionSales extends Model
{
    protected $table = 'transaction_sales';

    protected $fillable = [
        'sale_id',
        'uid',
        'cid',
        'customer_id',
        'total_amount',
        'payment_mode'
    ];
    public $timestamps = false;

}
