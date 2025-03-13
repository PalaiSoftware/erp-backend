<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesItem extends Model
{
    use HasFactory;

    protected $fillable = ['sale_id', 'quantity', 'discount', 'per_item_cost','unit_id'];

    // Relationship to sale (no foreign key enforced)
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}