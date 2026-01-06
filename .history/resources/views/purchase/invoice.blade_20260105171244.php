<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase Invoice</title>
    <style>
        /* DejaVu Sans = Perfect ₹ in DomPDF */
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
            font-size: 13px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1.invoice-title {
            text-align: center;
            font-size: 26px;
            color: #2c3e50;
            margin: 0 0 20px 0;
            font-weight: bold;
        }
        .top-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .logo-container img {
            max-width: 120px;
            height: auto;
        }
        .invoice-title-wrapper {
            flex: 1;
            text-align: center;
        }
        .header-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 20px 0;
            margin-bottom: 20px;
        }
        .company-info h4, .vendor-info h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #333;
            font-weight: bold;
        }
        .billing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .billing-table th, .billing-table td {
            padding: 7px 10px;
            border: 1px solid #ddd;
        }
        .billing-table th {
            background: #f4f4f4;
            text-align: left;
            color: #333;
            width: 38%;
        }

        /* Items Table */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
            table-layout: fixed;
        }
        table.items-table th, table.items-table td {
            border: 1px solid #ddd;
            padding: 8px 5px;
            text-align: center;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.items-table th {
            background-color: #f4f4f4;
            font-weight: 600; /* Light bold for headers */
            color: #333;
        }
        table.items-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Column Widths */
        .col-sn       { width: 5%; }
        .col-product  { width: 24%; text-align: left; }
        .col-hsn      { width: 8%; }
        .col-serial   { width: 18%; font-size: 9.5px; white-space: pre-line; word-break: break-all; line-height: 1.3; }
        .col-disc     { width: 9%; }
        .col-amount   { width: 11%; text-align: right; }
        .col-sgst     { width: 8%; }
        .col-cgst     { width: 8%; }
        .col-total    { width: 13%; text-align: right; }

        .quantity, .unit, .unit-price {
            font-size: 11px;
            color: #666;
        }

        /* Horizontal Totals Table */
        table.totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            font-size: 13px;
            table-layout: fixed;
        }
        table.totals-table th {
            background-color: #f4f4f4;
            font-weight: 600; /* Light bold - same as items table */
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
            color: #333;
        }
        table.totals-table td {
            padding: 12px 10px;
            text-align: right;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            font-weight: normal; /* Values normal */
        }
        /* Slight highlight for Due Amount row */
        .totals-due td {
            font-weight: normal;
            background-color: #ecf0f1;
            font-size: 14px;
        }

        .rupee {
            font-family: DejaVu Sans;
        }
    </style>
</head>
<body>
    <div class="invoice-container">

        <!-- Logo + Title -->
        <div class="top-header">
            <div class="logo-container">
                <img src="{{ public_path('images/logo.png') }}" alt="Company Logo">
            </div>
            <div class="invoice-title-wrapper">
                <h1 class="invoice-title">PURCHASE INVOICE</h1>
            </div>
        </div>

        <!-- Company & Vendor Details - Single Table -->
        <table class="header-table">
            <tr>
                <td class="company-info">
                    <h4>Company Details (Purchaser)</h4>
                    <table class="billing-table">
                        <tr><th>Name</th><td>{{ $company->name }}</td></tr>
                        @if(!empty($company->address) && trim($company->address) !== 'N/A')
                        <tr><th>Address</th><td>{{ $company->address }}</td></tr>
                        @endif
                        <tr><th>Phone</th><td>+91 {{ $company->phone }}</td></tr>
                        <tr><th>GSTIN</th><td>{{ $company->gst_no }}</td></tr>
                        <tr><th>PAN</th><td>{{ $company->pan }}</td></tr>
                    </table>
                </td>
                <td class="vendor-info">
                    <h4>Vendor Details (Supplier)</h4>
                    <table class="billing-table">
                        <tr><th>Bill Name</th><td>{{ $transaction->bill_name }}</td></tr>
                        <tr><th>Vendor Name</th><td>{{ $vendor->name }}</td></tr>
                        <tr><th>Phone</th><td>{{ $vendor->phone ?? 'N/A' }}</td></tr>
                        @if(!empty($vendor->gst_no) && trim($vendor->gst_no) !== 'N/A')
                        <tr><th>GSTIN</th><td>{{ $vendor->gst_no }}</td></tr>
                        @endif
                        <tr><th>Purchase Date</th><td>{{ \Carbon\Carbon::parse($transaction->updated_at)->format('d-m-Y h:i A') }}</td></tr>
                        <tr><th>Payment Mode</th><td>{{ $payment_mode }}</td></tr>
                        <tr><th>Purchased By</th><td>{{ $purchased_by }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-sn">SN</th>
                    <th class="col-product">Item Details</th>
                    <th class="col-hsn">HSN</th>
                    <th class="col-serial">Serial No(s)</th>
                    <th class="col-disc">Discount%</th>
                    <th class="col-amount">Amount</th>
                    <th class="col-sgst">SGST%</th>
                    <th class="col-cgst">CGST%</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $index => $item)
                    <tr>
                        <td class="col-sn">{{ $index + 1 }}</td>
                        <td class="col-product">
                            {{ $item->product_name }}<br>
                            <span class="quantity">{{ $item->quantity }}</span>
                            <span class="unit"> {{ $item->unit_name }}</span>
                            @ <span class="unit-price"><span class="rupee">₹</span>{{ number_format($item->per_item_cost, 2) }}</span>
                        </td>
                        <td class="col-hsn">{{ $item->hsn ?? '-' }}</td>
                        <td class="col-serial">{{ $item->serial_numbers ?? '-' }}</td>
                        <td class="col-disc">{{ $item->discount ?? 0 }}</td>
                        <td class="col-amount"><span class="rupee">₹</span>{{ number_format($item->net_price, 2) }}</td>
                        <td class="col-sgst">{{ ($item->gst ?? 0) / 2 }}</td>
                        <td class="col-cgst">{{ ($item->gst ?? 0) / 2 }}</td>
                        <td class="col-total">
                            <span class="rupee">₹</span>{{ number_format($item->per_product_total + $item->gst_amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;">No items found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <!-- Horizontal Totals Table -->
        <table class="totals-table">
            <thead>
                <tr>
                    <th>Total Items Value</th>
                    <th>Total GST</th>
                    <th>Gross Total</th>
                    @if($absolute_discount > 0)
                        <th>Absolute Discount</th>
                    @endif
                    <th>Payable Amount</th>
                    <th>Paid Amount</th>
                    <th>Due Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr @if($due_amount > 0) class="totals-due" @endif>
                    <td><span class="rupee"></span> {{ number_format($total_item_net_value, 2) }}</td>
                    <td><span class="rupee"></span> {{ number_format($total_gst_amount, 2) }}</td>
                    <td><span class="rupee"></span> {{ number_format($total_amount, 2) }}</td>
                    @if($absolute_discount > 0)
                        <td><span class="rupee"></span> {{ number_format($absolute_discount, 2) }}</td>
                    @endif
                    <td><span class="rupee"></span> {{ number_format($payable_amount, 2) }}</td>
                    <td><span class="rupee"></span> {{ number_format($paid_amount, 2) }}</td>
                    <td><span class="rupee"></span> {{ number_format($due_amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

    </div>
</body>
</html>