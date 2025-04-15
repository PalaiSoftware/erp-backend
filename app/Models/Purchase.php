<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['transaction_id', 'product_id','created_at',];
    public $timestamps = false;

    public function transaction()
    {
        return $this->belongsTo(TransactionPurchase::class, 'transaction_id');
    }

    public function purchaseItem()
    {
        return $this->hasOne(PurchaseItem::class, 'purchase_id');
    }
}