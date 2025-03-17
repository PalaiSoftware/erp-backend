<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    
    public function getTotalPurchases($cid)
    {
        if (!is_numeric($cid) || (int)$cid <= 0) {
            return response()->json(['error' => 'Invalid company ID'], 400);
        }
    
        $transactionIds = DB::table('transaction_purchases')
            ->where('cid', $cid)
            ->pluck('id')
            ->toArray();
    
        if (empty($transactionIds)) {
            return response()->json([
                'cid' => $cid,
                'total_purchase' => 0,
            ]);
        }
    
        $totalPurchase = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereIn('purchases.transaction_id', $transactionIds)
            ->sum(DB::raw('purchase_items.quantity * purchase_items.per_item_cost'));
    
        return response()->json([
            'cid' => $cid,
            'total_purchase' => $totalPurchase,
        ]);
    }
}