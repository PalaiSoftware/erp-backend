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
            [''], // Empty row for spacing
        ]);

        // Add headings
        $rows->push($this->headings());

        $sr = 1;
        foreach ($this->data as $row) {
            $rows->push([
                $sr++,
                $row->item_name,
                $row->hscode,
                $row->cgst_rate,
                $row->sgst_rate,
                $row->igst_rate,
                number_format((float)$row->taxable_amount, 2),
                number_format((float)$row->total_gst, 2),
                number_format((float)$row->total_amount, 2),
            ]);
        }

        // Grand Total Row
        $grandTaxable = $this->data->sum('taxable_amount');
        $grandGst     = $this->data->sum('total_gst');
        $grandTotal   = $this->data->sum('total_amount');

        $rows->push([
            '', '', '', '', '', '', 'TOTAL',
            number_format($grandTaxable, 2),
            number_format($grandGst, 2),
            number_format($grandTotal, 2)
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Sl', 'Item', 'HSN Code', 'CGST%', 'SGST%', 'IGST%', 'Amount', 'GST', 'Total'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Get last row (after total is added)
        $highestRow = $sheet->getHighestRow();

        return [
            // Title
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF000000']]],

            // Period
            2 => ['font' => ['italic' => true, 'size' => 11]],

            // Header row
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],

            // Total row (bold + background)
            $highestRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF0F0F0'],
                ],
            ],

            // Align numbers to right
            'G:' . $highestRow => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'H:' . $highestRow => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'I:' . $highestRow => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
        ];
    }

    public function title(): string
    {
        return 'B2C Sales Report';
    }
}