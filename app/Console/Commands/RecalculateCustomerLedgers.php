<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateCustomerLedgers extends Command
{
    protected $signature = 'ledger:recalculate';
    protected $description = 'Recalculate all customer ledger summaries';

    public function handle()
    {
        $this->info('Starting recalculation...');

        // Same rounding logic as your detailed ledger
        $billGross = DB::table('sales_items')
            ->select('bid', DB::raw('ROUND(SUM(s_price * quantity * (1 - dis / 100) * (1 + gst / 100)), 2) as gross_rounded'))
            ->groupBy('bid');

        $billPayments = DB::table('customer_bill_payments')
            ->select('bill_id', DB::raw('SUM(paid_amount) as paid'))
            ->groupBy('bill_id');

        $totals = DB::table('sales_clients as sc')
            ->join('sales_bills as sb', 'sc.id', '=', 'sb.scid')
            ->leftJoinSub($billGross, 'bg', fn($j) => $j->on('sb.id', '=', 'bg.bid'))
            ->leftJoinSub($billPayments, 'bp', fn($j) => $j->on('sb.id', '=', 'bp.bill_id'))
            ->select(
                'sc.id as customer_id',
                'sc.cid',
                DB::raw('SUM(COALESCE(bg.gross_rounded, 0) - sb.absolute_discount) as purchase'),
                DB::raw('SUM(COALESCE(bp.paid, 0)) as paid')
            )
            ->groupBy('sc.id', 'sc.cid')
            ->get();

        DB::table('customer_ledger_summaries')->truncate();

        foreach ($totals as $t) {
            $purchase = round($t->purchase, 2);
            $paid = round($t->paid, 2);
            $due = max(0, $purchase - $paid);

            DB::table('customer_ledger_summaries')->updateOrInsert(
                ['customer_id' => $t->customer_id, 'cid' => $t->cid],
                [
                    'total_purchase' => $purchase,
                    'total_paid' => $paid,
                    'total_due' => $due,
                    'updated_at' => now(),
                ]
            );
        }

        $this->info('Ledger summaries recalculated successfully!');
    }
};