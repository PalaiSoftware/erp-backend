<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncrementalPayment extends Model
{
    use HasFactory;

    protected $table = 'incremental_payments';

    protected $primaryKey = null; // No primary key

    public $incrementing = false; // No auto-incrementing key
    public $timestamps = false;
    protected $fillable = [
        'bid',
        'date',
        'amount',
    ];
}