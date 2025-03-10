<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'vendor_id', 'quantity', 'per_item_cost'];
    public $timestamps = false;

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}