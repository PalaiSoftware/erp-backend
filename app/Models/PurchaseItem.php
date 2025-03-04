<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_id', 'vendor_id', 'quantity', 'per_item_cost'];

    // Disable timestamps since there's no updated_at
    public $timestamps = false;

    // If you want only created_at, alternatively use:
    // const CREATED_AT = 'created_at';
    // const UPDATED_AT = null;
}