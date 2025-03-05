<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        .invoice-container {
            width: 100%;
            margin: 5px auto;
            font-family: Arial, sans-serif;
        }
        .top-table {
            width: 100%;
            border-collapse: collapse;
        }
        .top-table td {
            vertical-align: top;
            padding: 5px;
            width: 50%;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <table class="top-table">
            <tr>
                <td>
                    <div class="company-info">
                        <h3>Company Details</h3>
                        <p>Name: {{ $company->name }}</p>
                        <p>Address: {{$company->address}}</p>
                        <p>Phone: +91 {{$company->phone}}</p>
                        <p>GSTIN: {{$company->gst_no}}</p>
                        <p>PAN Number: {{$company->pan}}</p>
                    </div>
                </td>
                <td>
                    <div class="header">
                        <h1>Invoice</h1>
                        <p>Invoice Number: {{ $invoice->number }}</p>
                        <p>Date: {{ $invoice->date }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table">
            <thead>
                <tr>
                    <th>S.No.</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Discount(%)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ number_format($item['per_item_cost'], 2) }}</td>
                        <td>{{ $item['discount'] }}</td>
                        <td>{{ number_format($item['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No items found for this sale.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            <p><strong>Total Amount: {{ number_format($transaction->total_amount, 2) }}</strong></p>
        </div>
    </div>
</body>
</html>