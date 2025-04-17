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
        'absolute_discount',
        'paid_amount',
        'created_at',
        'updated_at',
    ];
}
