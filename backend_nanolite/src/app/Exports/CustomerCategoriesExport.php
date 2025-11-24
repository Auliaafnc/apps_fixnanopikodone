<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerCategoriesExport implements FromArray, WithStyles
{
    protected Collection $categories;

    public function __construct(Collection $categories)
    {
        $this->categories = $categories;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    public function array(): array
    {
        $rows = [
            ['', '', '', 'DATA KATEGORI CUSTOMER', '', '', '', ''],
            [
                'No.',
                'Nama Kategori',
                'Deskripsi',
                'Jumlah Pengguna',
                'Status',
                'Dibuat Pada',
                'Diupdate Pada',
            ],
        ];

        $no = 1;
        foreach ($this->categories as $category) {
            $rows[] = [
                $no++,
                $this->dashIfEmpty($category->name),
                $this->dashIfEmpty($category->deskripsi),
                $category->customers()->count() ?: '-',
                $this->dashIfEmpty(ucfirst($category->status)),
                optional($category->created_at)->format('Y-m-d H:i'),
                optional($category->updated_at)->format('Y-m-d H:i'),
            ];
        }

        $rows[] = array_fill(0, 6, '');

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Judul baris 1
        $sheet->getStyle('D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header baris 2
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F0F0'],
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Baris data
        $lastRow = count($this->categories) + 2;
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
