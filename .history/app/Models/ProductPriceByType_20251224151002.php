<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceByType extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'customer_type_id',
        'cid',
        'selling_price',
    ];

    // Optional relationships (helpful later)
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customerType()
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function company()
    {
        return $this->belongsTo(Client::class, 'cid');
    }
}