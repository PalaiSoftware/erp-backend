<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBill extends Model
{
    protected $table = 'purchase_bills';

    protected $fillable = [
        'bill_name',
        'pcid',
        'uid',
        'payment_mode',
        'absolute_discount',
        'paid_amount',
        'created_at',
        'updated_at',

    ];
     // Payment mode mapping: string to integer
    public static $paymentModeMap = [
        'credit_card' => 1,
        'debit_card' => 2,
        'cash' => 3,
        'upi' => 4,
        'bank_transfer' => 5,
        'online' => 6,
        'phonepe' => 7,
    ];
}