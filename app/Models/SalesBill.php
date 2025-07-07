<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesBill extends Model
{
    use HasFactory;
    protected $fillable = ['bill_name', 'scid', 'uid', 'payment_mode', 'absolute_discount', 'paid_amount'];

    public function items()
    {
        return $this->hasMany(SalesItem::class, 'bid');
    }
}
