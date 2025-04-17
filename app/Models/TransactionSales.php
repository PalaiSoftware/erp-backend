<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionSales extends Model
{
    protected $table = 'transaction_sales';

    protected $fillable = [
        'uid',
        'cid',
        'customer_id',
        'payment_mode',
        'absolute_discount', 
        'total_paid',       
        'updated_at'
    ];

    public $timestamps = true;

    // Relationship to sales (no foreign key enforced)
    public function sales()
    {
        return $this->hasMany(Sale::class, 'transaction_id');
    }
}