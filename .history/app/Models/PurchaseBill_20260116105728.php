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

    public function payments()
{
    return $this->hasMany(VendorBillPayment::class, 'bill_id');
}
    
}