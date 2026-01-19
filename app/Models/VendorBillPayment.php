<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorBillPayment extends Model
{
    use HasFactory;

    protected $table = 'vendor_bill_payments';

    protected $fillable = [
        'vendor_id',
        'bill_id',
        'paid_amount',
        'paid_on',
        'payment_mode',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'paid_amount'  => 'decimal:2',
        'paid_on'      => 'date:Y-m-d',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function bill()
    {
        return $this->belongsTo(PurchaseBill::class, 'bill_id');
    }

    public function vendor()
    {
        return $this->belongsTo(PurchaseClient::class, 'vendor_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}