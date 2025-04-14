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
        .company-info p, .billing-info p {
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
        }
        table.items-table th,
        table.items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table.items-table th {
            background-color: #f4f4f4;
            font-weight: bold;
            font-size: 13px;
            color: #333;
        }
        table.items-table td {
            font-size: 13px;
            color: #555;
        }

        /* Footer Section */
        .footer-section {
            margin-top: 15px;
            text-align: right;
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
                    <p><strong>Invoice Number:</strong> {{ $invoice->number }}</p>
                    <p><strong>Date:</strong> {{ $invoice->date }}</p>
                    <p><strong>Date:</strong> {{ $transaction->updated_at }}</p>

                    <p><strong>Billing Done By:</strong> {{ $userDetails->name }}</p>

                    <h4>Billed To</h4>
                    @isset($customer)
                        @if(!is_null($customer->first_name))
                            <p><strong>Customer Name:</strong> {{ $customer->first_name }} {{ $customer->last_name ? $customer->last_name : '' }}</p>
                        @endif
                        @if(!is_null($customer->phone))
                            <p><strong>Phone:</strong> {{ $customer->phone }}</p>
                        @endif
                        @if(!is_null($customer->address))
                            <p><strong>Address:</strong> {{ $customer->address }}</p>
                        @endif
                        @if(!is_null($customer->email))
                            <p><strong>Email:</strong> {{ $customer->email }}</p>
                        @endif
                        @if($customer->gst)
                        <p><strong>GST:</strong> {{ $customer->gst }}</p>
                        @endif
                        @if($customer->pan)
                        <p><strong>PAN:</strong> {{ $customer->pan }}</p>
                        @endif
                    @else
                        <p>No customer information available</p>
                    @endisset
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>S.No.</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Price/Unit</th>
                    <th>Discount (%)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ $item['unit'] }}</td>
                        <td><span class="currency-symbol">Rs. </span> {{ number_format($item['per_item_cost'], 2) }}</td>
                        <td>{{ $item['discount'] }}</td>
                        <td><span class="currency-symbol">Rs.  </span> {{ number_format($item['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No items found for this sale.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer Section -->
        <div class="footer-section">
            <p><strong>Total Amount:</strong> <span class="currency-symbol">Rs.</span> {{ number_format($total_amount, 2) }}</p>
        </div>
    </div>
</body>
</html>