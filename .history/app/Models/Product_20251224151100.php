<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'hscode',
        'p_unit',
        's_unit',
        'c_factor',
        'cid',
        'uid',
        'description',
    ];
    // Define the relationship with Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function pricesByType()
{
    return $this->hasMany(ProductPriceByType::class, 'product_id');
}


}
