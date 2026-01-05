<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase Invoice</title>
    <style>
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
            padding: 30px;
            background-color: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        h1.invoice-title {
            text-align: center;
            font-size: 28px;
            color: #2c3e50;
            margin: 0 0 20px 0;
            font-weight: bold;
        }
        .top-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 15px;
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
            border-spacing: 15px 5px;
            margin-bottom: 20px;
        }
        .company-info, .vendor-info {
            width: 50%;
            vertical-align: top;
        }
        .company-info h4, .vendor-info h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .billing-table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }
        .billing-table th {
            text-align: left;
            background: #f8f9fa;
            padding: 8px;
            width: 35%;
            color: #34495e;
        }
        .billing-table td {
            padding: 8px;
            border-bottom: 1px dotted #ddd;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        table.items-table th {
            background-color: #3498db;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        table.items-table td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
            font-size: 12px;
        }
        table.items-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .footer-section {
            margin-top: 30px;
            text-align: right;
        }
        .footer-section table {
            width: 400px;
            float: right;
            border-collapse: collapse;
        }
        .footer-section th, .footer-section td {
            padding: 8px;
            border: 1px solid #bbb;
            text-align: right;
        }
        .footer-section th {
            background: #f4f4f4;
            width: 150px;
        }
        .total-row {
            font-weight: bold;
            font-size: 15px;
            background: #ecf0f1 !important;
        }
        .currency {
            font-weight: bold;
        }
        .page-break { page-break-after: always; }
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

        <!-- Company & Vendor Info -->
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
                    <th>S.No</th>
                    <th>Product</th>
                    <th>HSN</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Rate (₹)</th>
                    <th>Discount %</th>
                    <th>Net Rate</th>
                    <th>GST %</th>
                    <th>GST Amt</th>
                    <th>Total (₹)</th>
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

        <!-- Totals Footer -->
        <div class="footer-section">
            <table>
                <tr>
                    <th>Total Items Value</th>
                    <td><span class="currency">₹</span> {{ number_format($total_item_net_value, 2) }}</td>
                </tr>
                <tr>
                    <th>Total GST</th>
                    <td><span class="currency">₹</span> {{ number_format($total_gst_amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Gross Total</th>
                    <td><span class="currency">₹</span> {{ number_format($total_amount, 2) }}</td>
                </tr>
                @if($absolute_discount > 0)
                <tr>
                    <th>Absolute Discount</th>
                    <td><span class="currency">₹</span> {{ number_format($absolute_discount, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <th>Payable Amount</th>
                    <td><span class="currency">₹</span> {{ number_format($payable_amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Paid Amount</th>
                    <td><span class="currency">₹</span> {{ number_format($paid_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <th>Due Amount</th>
                    <td><span class="currency">₹</span> {{ number_format($due_amount, 2) }}</td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>
    </div>
</body>
</html>