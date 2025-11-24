<?php

namespace App\Exports;

use App\Models\Department;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DepartmentExport implements FromArray, WithStyles
{
    protected $departments;

    public function __construct($departments)
    {
        $this->departments = $departments;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    public function array(): array
    {
        $query = Department::query();
        $departments = $query
                ->orderBy('status')
                ->get();

        $rows = [
            ['', '', '', 'DATA DEPARTEMEN', '', '', ''],
            [
                'No.',
                'Nama',
                'Jumlah Pengguna',
                'Status',
                'Dibuat',
                'Diupdate',
            ],
        ];

        $no = 1;
        foreach ($this->departments as $dept) {
            $rows[] = [
                $no++,
                $this->dashIfEmpty($dept->name),
                $dept->employees()->count() ?: '-',
                $this->dashIfEmpty(ucfirst($dept->status)),
                optional($dept->created_at)->format('Y-m-d H:i'),
                optional($dept->updated_at)->format('Y-m-d H:i'),
            ];
        }

        $rows[] = array_fill(0, 6, ''); // baris jarak

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Judul
        $sheet->mergeCells('D1:E1');
        $sheet->getStyle('D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header
        $sheet->getStyle('A2:F2')->applyFromArray([
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
        $lastRow = count($this->departments) + 2;
        foreach (range(3, $lastRow) as $row) {
            foreach (range('A', 'F') as $col) {
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

        // Auto width
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
