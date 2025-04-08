<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'purchase_id',
        'vendor_id',
        'quantity',
        'per_item_cost',
        'discount',
        'unit_id',
        'created_at',
    ];

    // Relationship with Unit model
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    // Relationship with Purchase model (if needed)
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}