<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'hscode',
        'uid',
        'cid',
    ];
    // protected $casts = [
    //     'cids' => 'array',
    // ];

    // Define the relationship with Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

}
