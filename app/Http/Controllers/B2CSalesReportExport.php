<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class B2CSalesReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $data;
    protected $dateRange;

    public function __construct($data, $dateRange)
    {
        $this->data = $data;
        $this->dateRange = $dateRange;
    }

    public function collection()
    {
        $rows = collect([
            ['B2C SALES REPORT (Without Customer GSTIN)'],
            ['Period: ' . $this->dateRange],
            [''], // spacing
            ['Sl', 'Item', 'HSN Code', 'CGST%', 'SGST%', 'IGST%', 'Amount', 'GST', 'Total'],
        ]);

        $sr = 1;
        foreach ($this->data as $row) {
            $rows->push([
                $sr++,
                $row->item_name,
                $row->hscode,
                $row->cgst_rate,
                $row->sgst_rate,
                $row->igst_rate,
                number_format($row->taxable_amount, 2),
                number_format($row->total_gst, 2),
                number_format($row->total_amount, 2),
            ]);
        }

        // Grand Total
        $rows->push([
            '', '', '', '', '', '', 'TOTAL',
            number_format($this->data->sum('taxable_amount'), 2),
            number_format($this->data->sum('total_gst'), 2),
            number_format($this->data->sum('total_amount'), 2),
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        // We know exact row numbers because we built the sheet manually
        $headerRow = 4;
        $totalRow  = $this->data->count() + $headerRow + 1; // +1 for header spacing

        return [
            // Title
            1 => ['font' => ['bold' => true, 'size' => 16]],

            // Period
            2 => ['font' => ['italic' => true]],

            // Header Row
            $headerRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],

            // Total Row
            $totalRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF0F0F0'],
                ],
            ],

            // Right-align Amount, GST, Total columns (G, H, I)
            "G{$headerRow}:I{$totalRow}" => [
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
            ],
        ];
    }

    public function title(): string
    {
        return 'B2C Sales Report';
    }
}