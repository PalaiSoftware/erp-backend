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
            [''],
            $this->headings(),
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

        $grandTaxable = $this->data->sum('taxable_amount');
        $grandGst     = $this->data->sum('total_gst');
        $grandTotal   = $this->data->sum('total_amount');

        $rows->push(['', '', '', '', '', '', 'TOTAL',
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
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['italic' => true]],
            4 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE0E0E0'],
            ]],
            $sheet->getHighestRow() => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'B2C Sales Report';
    }
}