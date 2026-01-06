<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Invoice</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif; /* Good for PDF Unicode support */
            font-size: 10px;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            padding: 5px;
            vertical-align: top;
        }
        .items-table {
            table-layout: fixed; /* Forces fixed column widths */
            margin-top: 20px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            word-wrap: break-word; /* Prevents long text/numbers from overflowing */
            overflow-wrap: break-word;
        }
        /* Adjust column widths as needed (total must ≈100%) */
        .col-sno { width: 5%; }
        .col-product { width: 25%; text-align: left; }
        .col-hsn { width: 8%; }
        .col-qty { width: 8%; }
        .col-unit { width: 8%; }
        .col-rate { width: 10%; text-align: right; }
        .col-disc { width: 6%; text-align: right; }
        .col-net { width: 10%; text-align: right; }
        .col-gst-percent { width: 6%; text-align: right; }
        .col-gst-amt { width: 8%; text-align: right; }
        .col-total { width: 12%; text-align: right; }

        .totals-table {
            table-layout: fixed;
            margin-top: 20px;
            float: right; /* Aligns totals to the right side */
            width: auto;
        }
        .totals-table td {
            border: none;
            padding: 5px 10px;
            text-align: right;
        }
        .totals-label { text-align: left; width: 150px; }
        .totals-value { width: 120px; font-weight: bold; }
    </style>
</head>
<body>

    <!-- Company and Vendor Details -->
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <strong>Company Details (Purchaser)</strong><br>
                @if(!empty($company->address) && trim($company->address) !== 'N/A')
                    Address: {{ $company->address }}<br>
                @endif
                Name: {{ $company->name }}<br>
                Phone: +91 {{ $company->phone }}<br>
                GSTIN: {{ $company->gst_no }}<br>
                PAN: {{ $company->pan }}
            </td>
            <td style="width: 50%;">
                <strong>Vendor Details (Supplier)</strong><br>
                Bill Name: {{ $transaction->bill_name }}<br>
                Vendor Name: {{ $vendor->name }}<br>
                Phone: {{ $vendor->phone ?? 'N/A' }}<br>
                @if(!empty($vendor->gst_no) && trim($vendor->gst_no) !== 'N/A')
                    GSTIN: {{ $vendor->gst_no }}<br>
                @endif
                Purchase Date: {{ \Carbon\Carbon::parse($transaction->updated_at)->format('d-m-Y h:i A') }}<br>
                Payment Mode: {{ $payment_mode }}<br>
                Purchased By: {{ $purchased_by }}
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="col-sno">S.No</th>
                <th class="col-product">Product</th>
                <th class="col-hsn">HSN</th>
                <th class="col-qty">Qty</th>
                <th class="col-unit">Unit</th>
                <th class="col-rate">Rate (₹)</th>
                <th class="col-disc">Discount %</th>
                <th class="col-net">Net Rate</th>
                <th class="col-gst-percent">GST %</th>
                <th class="col-gst-amt">GST Amt</th>
                <th class="col-total">Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: left;">{{ $item->product_name }}</td>
                    <td>{{ $item->hsn ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->unit_name }}</td>
                    <td>{{ number_format($item->per_item_cost, 2) }}</td>
                    <td>{{ $item->discount ?? 0 }}</td>
                    <td>{{ number_format($item->net_price, 2) }}</td>
                    <td>{{ $item->gst ?? 0 }}</td>
                    <td>{{ number_format($item->gst_amount, 2) }}</td>
                    <td>{{ number_format($item->per_product_total + $item->gst_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="11">No items found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- Totals Section (aligned like a table, right side) -->
    @if($absolute_discount > 0 || true) <!-- Adjust condition -->
        <div style="clear: both;"></div>
        <table class="totals-table">
            <tr><td class="totals-label">Total Items Value</td><td class="totals-value">₹ {{ number_format($total_item_net_value, 2) }}</td></tr>
            <tr><td class="totals-label">Total GST</td><td class="totals-value">₹ {{ number_format($total_gst_amount, 2) }}</td></tr>
            <tr><td class="totals-label">Gross Total</td><td class="totals-value">₹ {{ number_format($total_amount, 2) }}</td></tr>
            @if($absolute_discount > 0)
                <tr><td class="totals-label">Absolute Discount</td><td class="totals-value">₹ {{ number_format($absolute_discount, 2) }}</td></tr>
            @endif
            <tr><td class="totals-label">Payable Amount</td><td class="totals-value">₹ {{ number_format($payable_amount, 2) }}</td></tr>
            <tr><td class="totals-label">Paid Amount</td><td class="totals-value">₹ {{ number_format($paid_amount, 2) }}</td></tr>
            <tr><td class="totals-label">Due Amount</td><td class="totals-value">₹ {{ number_format($due_amount, 2) }}</td></tr>
        </table>
    @endif

</body>
</html>