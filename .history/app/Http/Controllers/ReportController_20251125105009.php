<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function getProfitLossReport(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Only allow roles 1,2,3,4 (admin, manager, etc.) → change if your roles are different
        if (!in_array($user->rid, [1, 2, 3, 4])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $cid = $user->cid; // your company id

        // Validate dates
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate   = Carbon::parse($request->end_date)->endOfDay();

        try {
            // TOTAL PURCHASE AMOUNT (including GST & item discount, minus bill discount)
            $purchase = DB::table('purchase_items as pi')
                ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
                ->join('users as u', 'pb.uid', '=', 'u.id')
                ->where('u.cid', $cid)
                ->whereBetween('pb.updated_at', [$startDate, $endDate])
                ->selectRaw("
                    COALESCE(SUM(pi.quantity * pi.p_price * (1 - COALESCE(pi.dis,0)/100) * (1 + COALESCE(pi.gst,0)/100)), 0) as items_total,
                    COALESCE(SUM(pb.absolute_discount), 0) as bill_discount
                ")
                ->first();

            $totalPurchase = $purchase->items_total - $purchase->bill_discount;

            // TOTAL SALES AMOUNT (including GST & item discount, minus bill discount)
            $sales = DB::table('sales_items as si')
                ->join('sales_bills as sb', 'si.bid', '=', 'sb.id')
                ->join('users as u', 'sb.uid', '=', 'u.id')
                ->where('u.cid', $cid)
                ->whereBetween('sb.updated_at', [$startDate, $endDate])
                ->selectRaw("
                    COALESCE(SUM(si.quantity * si.s_price * (1 - COALESCE(si.dis,0)/100) * (1 + COALESCE(si.gst,0)/100)), 0) as items_total,
                    COALESCE(SUM(sb.absolute_discount), 0) as bill_discount
                ")
                ->first();

            $totalSales = $sales->items_total - $sales->bill_discount;

            // Profit Calculation
            $grossProfit = $totalSales - $totalPurchase;
            $profitPercentage = $totalPurchase > 0 ? round(($grossProfit / $totalPurchase) * 100, 2) : 0;

            // Bill Counts
            $purchaseBills = DB::table('purchase_bills as pb')
                ->join('users as u', 'pb.uid', '=', 'u.id')
                ->where('u.cid', $cid)
                ->whereBetween('pb.updated_at', [$startDate, $endDate])
                ->count();

            $salesBills = DB::table('sales_bills as sb')
                ->join('users as u', 'sb.uid', '=', 'u.id')
                ->where('u.cid', $cid)
                ->whereBetween('sb.updated_at', [$startDate, $endDate])
                ->count();

            return response()->json([
                'status' => 'success',
                'date_range' => [
                    'start' => $request->start_date,
                    'end'   => $request->end_date,
                ],
                'summary' => [
                    'total_purchase'       => round($totalPurchase, 2),
                    'total_sales'          => round($totalSales, 2),
                    'gross_profit'         => round($grossProfit, 2),
                    'profit_percentage'    => $profitPercentage . '%',
                    'purchase_bills_count' => $purchaseBills,
                    'sales_bills_count'    => $salesBills,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profit Loss Report Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}