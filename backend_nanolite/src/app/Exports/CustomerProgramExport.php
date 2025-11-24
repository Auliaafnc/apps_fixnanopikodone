<?php

namespace App\Exports;

use App\Models\CustomerProgram;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerProgramExport implements FromArray, WithStyles
{
    protected $programs;

    public function __construct($programs)
    {
        $this->programs = $programs;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    public function array(): array
    {
        $rows = [
            ['', '', '', 'DATA PROGRAM PELANGGAN', '', '', '', ''],
            [
                'No.',
                'Nama Program',
                'Deskripsi',
                'Jumlah Pengguna',
                'status',
                'Dibuat Pada',
                'Diupdate Pada',
            ],
        ];

        $no = 1;
        foreach ($this->programs as $prog) {
            $rows[] = [
                $no++,
                $this->dashIfEmpty($prog->name ?? '-'),
                $this->dashIfEmpty($prog->deskripsi ?? '-'),
                $prog->customers()->count() ?: '-',
                $this->dashIfEmpty(ucfirst($prog->status)),
                optional($prog->created_at)->format('Y-m-d H:i'),
                optional($prog->updated_at)->format('Y-m-d H:i'),
            ];
        }

        $rows[] = array_fill(0, 6, '');

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Judul
        $sheet->getStyle('D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F0F0'],
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data
        $lastRow = count($this->programs) + 2;
        foreach (range(3, $lastRow) as $row) {
            foreach (range('A', 'G') as $col) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
            }
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
