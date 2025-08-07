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
       // 'cid',
    ];
    // Define the relationship with Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    // public function productValue()
    // {
    //     return $this->hasOne(ProductValue::class, 'pid');
    // }


}
