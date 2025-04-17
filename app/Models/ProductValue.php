<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductValue extends Model
{
    use HasFactory;

    protected $table = 'product_values';
    protected $primaryKey = 'pid';
    public $incrementing = false;

    protected $fillable = [
        'sale_discount_percent',
        'sale_discount_flat',
        'selling_price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'pid');
    }
}