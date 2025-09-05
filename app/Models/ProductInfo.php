<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductInfo extends Model
{
    use HasFactory;
    // Specify the table name since it’s not the plural 'product_infos'
    protected $table = 'product_info';
    protected $primaryKey = null; // No primary key

    public $incrementing = false; // No auto-incrementing key
    // Define fillable fields for mass assignment
    protected $fillable = [
        'pid',
        'hsn_code',
        'description',
        'unit_id',
        'purchase_price',
        'profit_percentage',
        'pre_gst_sale_cost',
        'gst',
        'post_gst_sale_cost',
        'uid',
        'cid',
    ];

    // Cast decimal fields to ensure proper handling
    protected $casts = [
        'purchase_price' => 'decimal:2',
        'profit_percentage' => 'decimal:2',
        'pre_gst_sale_cost' => 'decimal:2',
        'gst' => 'decimal:2',
        'post_gst_sale_cost' => 'decimal:2',
    ];
}
