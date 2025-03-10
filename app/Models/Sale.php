<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = ['transaction_id', 'product_id'];

    public $incrementing = true; // Auto-incrementing id
    protected $primaryKey = 'id'; // Primary key is id

    // Relationship to transaction_sales (no foreign key enforced)
    public function transaction()
    {
        return $this->belongsTo(TransactionSales::class, 'transaction_id');
    }

    // Relationship to sales_items (no foreign key enforced)
    public function salesItem()
    {
        return $this->hasOne(SalesItem::class, 'sale_id');
    }
}