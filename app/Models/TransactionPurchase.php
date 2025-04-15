<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionPurchase extends Model
{
    protected $table = 'transaction_purchases';

    protected $fillable = [
        'purchase_id',
        'uid',
        'cid',
        // 'total_amount',
        'payment_mode',
        'created_at',
        'updated_at',
    ];
}
