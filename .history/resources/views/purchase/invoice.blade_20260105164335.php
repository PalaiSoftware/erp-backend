<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase Invoice</title>
    <style>
        /* General Styles */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
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
            font-size: 24px;
            color: #333;
            margin: 0;
            font-weight: bold;
        }

        /* Top Header */
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

        /* Header Tables */
        .header-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 20px 0;
            margin-bottom: 20px;
        }
        .company-info, .vendor-info {
            width: 50%;
        }
        .company-info h4, .vendor-info h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #333;
        }

        /* Billing Table Style (used for details) */
        .billing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .billing-table th,
        .billing-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }
        .billing-table th {
            background: #f4f4f4;
            text-align: left;
            color: #333;
            width: 35%;
        }
        .billing-table td {
            background: #fff;
        }

        /* Items Table */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
            table-layout: fixed;
        }
        table.items-table th,
        table.items-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.items-table th {
            background-color: #f4f4f4;
            font-weight: bold;
            color: #333;
        }
        table.items-table td {
            color: #555;
        }
        /* Column widths & alignment */
        .col-sn       { width: 5%; text-align: center; }
        .col-product  { width: 22%; }
        .col-hsn      { width: 8%; text-align: center; }
        .col-serial   { width: 18%; font-size: 9px; white-space: pre-line; word-break: break-all; }
        .col-disc     { width: 8%; text-align: center; }
        .col-amount   { width: 10%; text-align: right; }
        .col-sgst     { width: 7%; text-align: center; }
        .col-cgst     { width: 7%; text-align: center; }
        .col-total    { width: 15%; text-align: right; font-weight: bold; }

        /* Quantity & Rate inside product cell */
        .quantity, .unit, .unit-price {
            font-size: 11px;
            color: #666;
        }

        /* Footer Totals */
        .footer-section {
            margin-top: 20px;
            text-align: right;
        }
        .footer-section table {
            width: auto;
            display: inline-table;
            border-collapse: collapse;
            font-size: 14px;
        }
        .footer-section th,
        .footer-section td {
            padding: 6px 12px;
            text-align: left;
            border: none;
        }
        .footer-section th {
            font-weight: normal;
            color: #333;
        }
        .footer-section td {
            font-weight: bold;
            text-align: right;
            min-width: 120px;
        }
        .total-row td {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            border-top: 2px solid #333;
            padding-top: 10px;
        }

        /* Currency */
        .currency-symbol {
            margin-right: 3px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">

        <!-- Top Header: Logo + Title -->
        <div class="top-header">
            <div class="logo-container">
                <img src="{{ public_path('images/logo.png') }}" alt="Company Logo">
            </div>
            <div class="invoice-title-wrapper">
                <h1 class="invoice-title">PURCHASE INVOICE</h1>
            </div>
        </div>

        <!-- Company & Vendor Details -->
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

                <td class="vendor-info" style="text-align: right;">
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
                    <th>Item Details</th>
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
                        <td class="col-sn">{{ $index + 1 }}.</td>
                        <td>
                            {{ $item->product_name }}<br>
                            <span class="quantity">{{ $item->quantity }}</span>
                            <span class="unit"> {{ $item->unit_name }}</span>
                            @ <span class="unit-price">{{ number_format($item->per_item_cost, 2) }}</span>
                        </td>
                        <td class="col-hsn">{{ $item->hsn ?? '-' }}</td>
                        <td class="col-serial">{{ $item->serial_numbers ?? '-' }}</td>
                        <td class="col-disc">{{ $item->discount ?? 0 }}</td>
                        <td class="col-amount">{{ number_format($item->net_price, 2) }}</td>
                        <td class="col-sgst">{{ ($item->gst ?? 0) / 2 }}</td>
                        <td class="col-cgst">{{ ($item->gst ?? 0) / 2 }}</td>
                        <td class="col-total">
                            <span class="currency-symbol">₹</span>{{ number_format($item->per_product_total + $item->gst_amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">No items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer Totals -->
        <div class="footer-section">
            <table>
                <tr>
                    <th>Total Items Value</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($total_item_net_value, 2) }}</td>
                </tr>
                <tr>
                    <th>Total GST</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($total_gst_amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Gross Total</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($total_amount, 2) }}</td>
                </tr>
                @if($absolute_discount > 0)
                <tr>
                    <th>Absolute Discount</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($absolute_discount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <th>Payable Amount</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($payable_amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Paid Amount</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($paid_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <th>Due Amount</th>
                    <td><span class="currency-symbol">₹</span> {{ number_format($due_amount, 2) }}</td>
                </tr>
            </table>
        </div>

    </div>
</body>
</html>