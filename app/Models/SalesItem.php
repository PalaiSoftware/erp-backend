<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    use HasFactory;

    protected $table = 'sales_items';

    protected $primaryKey = null; // No primary key

    public $incrementing = false; // No auto-incrementing key
    public $timestamps = false;
    protected $fillable = [
        'bid',
        'pid',
        'p_price',
        's_price',
        'quantity',
        'unit_id',
        'dis',
    ];

    // Relationship with Unit model
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}