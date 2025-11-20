<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice</title>
    <style>
        /* General Styles */
        body {
            font-family: 'Segoe UI Emoji', 'Arial Unicode MS', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
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
            margin-bottom: 15px;
        }

        /* Header Section */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .company-info, .billing-info {
            width: 50%;
        }
        .company-info img {
            max-width: 120px;
            height: auto;
            margin-bottom: 8px;
        }
        .company-info p {
            margin: 4px 0;
            font-size: 13px;
            color: #555;
        }
        .billing-info h4 {
            margin: 8px 0;
            font-size: 15px;
            color: #333;
        }

        /* Items Table */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            page-break-inside: auto;
        }
        table.items-table th,
        table.items-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-size: 12px;
        }
        table.items-table th {
            background-color: #f4f4f4;
            font-weight: bold;
            color: #333;
        }
        table.items-table td {
            color: #555;
        }

        /* Keep table header/footer inside PDF pages */
        thead {
            display: table-header-group; /* Repeats header on each page */
        }
        tfoot {
            display: table-row-group;
        }
        tr {
            page-break-inside: avoid; /* Don’t split rows across pages */
        }

        /* Footer Section */
        .footer-section {
            margin-top: 15px;
            text-align: right;
            page-break-inside: avoid;
        }
        .footer-section p {
            margin: 4px 0;
            font-size: 14px;
            color: #333;
        }
        .footer-section strong {
            font-size: 16px;
            color: #000;
        }

        /* Currency styling */
        .currency-symbol {
            font-family: 'Segoe UI Emoji', 'Arial Unicode MS', sans-serif;
            margin-right: 2px;
        }

        /* Billing Details Table */
        .billing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .billing-table th,
        .billing-table td {
            padding: 6px;
            border: 1px solid #ddd;
        }
        .billing-table th {
            background: #f4f4f4;
            text-align: left;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Title -->
        <h1 class="invoice-title">Invoice</h1>

        <!-- Header Section -->
        <table class="header-table">
            <tr>
                <!-- Company Info -->
                <td class="company-info">
                    <img src="{{ public_path('images/logo.png') }}" alt="Company Logo" class="logo">
                    <p><strong>Name:</strong> {{ $company->name }}</p>
                    <p><strong>Address:</strong> {{ $company->address }}</p>
                    <p><strong>Phone:</strong> +91 {{ $company->phone }}</p>
                    <p><strong>GSTIN:</strong> {{ $company->gst_no }}</p>
                    <p><strong>PAN Number:</strong> {{ $company->pan }}</p>
                </td>

                <!-- Billing Info -->
                <td class="billing-info" style="text-align: right;">
                    <h4>Billing Details</h4>
                    <table class="billing-table">
                        <tr>
                            <th>Invoice Number</th>
                            <td>{{ $invoice->number }}</td>
                        </tr>
                        <tr>
                            <th>Bill Date</th>
                            <td>{{ $transaction->updated_at }}</td>
                        </tr>
                        <tr>
                            <th>Payment Mode</th>
                            <td>{{ $payment_mode }}</td>
                        </tr>
                        <tr>
                            <th>Customer Name</th>
                            <td>{{ $customer->name }}</td>
                        </tr>
                        <tr>
                            <th>Phone Number</th>
                            <td>{{ $customer->phone }}</td>
                        </tr>
                         <!-- Conditional Customer GSTIN display - FIXED VERSION -->
                         @php
                            $hasValidGst = !empty($customer->gst_no) && 
                                            $customer->gst_no != 'N/A' && 
                                            $customer->gst_no != 'null' && 
                                            $customer->gst_no != '0' && 
                                            $customer->gst_no != '0.00' && 
                                            $customer->gst_no != '0.0' && 
                                            $customer->gst_no != '0.000' && 
                                            strlen(trim($customer->gst_no)) > 0;
                        @endphp

                        @if($hasValidGst)
                        <tr>
                            <th>Customer GSTIN</th>
                            <td>{{ $customer->gst_no }}</td>
                        </tr>
                        @endif
                        
                        <!-- Conditional Customer PAN display - FIXED VERSION -->
                        @php
                            $hasValidPan = !empty($customer->pan) && 
                                            $customer->pan != 'N/A' && 
                                            $customer->pan != 'null' && 
                                            $customer->pan != '0' && 
                                            $customer->pan != '0.00' && 
                                            $customer->pan != '0.0' && 
                                            $customer->pan != '0.000' && 
                                            strlen(trim($customer->pan)) > 0;
                        @endphp

                        @if($hasValidPan)
                        <tr>
                            <th>Customer PAN</th>
                            <td>{{ $customer->pan }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Details</th>
                    <th>HSN</th>
                    <th>Discount (%)</th>
                    <th>Amount</th>
                     <!-- <th>Subtotal (excl. GST)</th> -->
                    <th>SGST (%)</th>
                    <th>CGST (%)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                       <td class="item-details">
                            <span class="sno">{{ $loop->index + 1 }}.</span> 
                            {{ $item['product_name'] }} 
                            <span class="quantity">{{ $item['quantity'] }}</span> 
                            <span class="unit">{{ $item['unit'] }}</span> 
                            @ <span class="unit-price">{{ number_format($item['per_item_cost'], 2) }}</span>
                        </td>
                        <td>{{ $item['hsn'] }}</td>
                        <td>{{ $item['discount'] }}</td>
                        <td>{{ $item['amount'] }}</td>
                        <!-- <td><span class="currency-symbol">Rs. </span>{{ number_format($item['net_price'], 2) }}</td> -->
                        <!--  <td><span class="currency-symbol">Rs. </span>{{ number_format($item['per_product_total'], 2) }}</td> -->
                        <td>{{ $item['gst']/2 }}</td>
                        <td>{{ $item['gst']/2 }}</td>
                        <!-- <td><span class="currency-symbol">Rs.</span>{{ number_format($item['gst_amount'], 2) }}</td> -->
                        <td><span class="currency-symbol">Rs.</span>{{ number_format($item['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No items found for this sale.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer Section -->
        <!-- Footer Section -->
<<!-- Footer Section -->
<div class="footer-section" style="width: 100%; margin-top: 10px;">
    <table style="width: 40%; border-collapse: collapse; font-size: 14px; margin-left: auto;">
        <tbody>
            @if($total_gst_amount > 0)
            <tr>
                <td style="padding:6px; border:1px solid #ddd;"><strong>Total Net Value (excl. GST):</strong></td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($total_item_net_value, 2) }}
                </td>
            </tr>
            @endif

            @if($total_gst_amount > 0)
            <tr>
                <td style="padding:6px; border:1px solid #ddd;"><strong>Total GST Amount:</strong></td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($total_gst_amount, 2) }}
                </td>
            </tr>
            @endif

            <tr>
                <td style="padding:6px; border:1px solid #ddd;"><strong>Total Amount:</strong></td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($total_amount, 2) }}
                </td>
            </tr>

            @if($absolute_discount > 0)
            <tr>
                <td style="padding:6px; border:1px solid #ddd;">Extra Discount:</td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($absolute_discount, 2) }}
                </td>
            </tr>
            @endif

            <tr>
                <td style="padding:6px; border:1px solid #ddd;"><strong>Payable Amount:</strong></td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($payable_amount, 2) }}
                </td>
            </tr>

            <tr>
                <td style="padding:6px; border:1px solid #ddd;">Paid Amount:</td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($paid_amount, 2) }}
                </td>
            </tr>

            <tr>
                <td style="padding:6px; border:1px solid #ddd;"><strong>Due Amount:</strong></td>
                <td style="padding:6px; border:1px solid #ddd; text-align:right;">
                    <span class="currency-symbol">Rs.</span>{{ number_format($due_amount, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

    </div>
</body>
</html>
