<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters = [];
    protected ?Collection $data = null;
    protected ?Collection $employeesForDrawing = null;

    public function __construct($input = [])
    {
        if ($input instanceof Collection) {
            $this->data = $input;
        } elseif (is_array($input)) {
            $this->filters = $input;
        }
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    public function array(): array
    {
        if ($this->data) {
            $employees = $this->data;
        } else {
            $query = Employee::with('department');

            if (!empty($this->filters['department_id'])) {
                $query->where('department_id', $this->filters['department_id']);
            }

            if (!empty($this->filters['status'])) {
                $query->where('employees.status', $this->filters['status']);
            }

            $employees = $query
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->orderBy('departments.name')
                ->orderBy('employees.status')
                ->select('employees.*')
                ->get();
        }

        $this->employeesForDrawing = $employees;

        $rows = [
            ['', '', '', '', '', 'DATA KARYAWAN', '', '', '', '', ''],
            ['No.', 'ID', 'Foto', 'Nama', 'Departemen', 'Email', 'Telepon', 'Alamat', 'Status', 'Dibuat Pada', 'Diupdate Pada'],
        ];

        $no = 1;
        foreach ($employees as $employee) {
            $rows[] = [
                $no++,
                $employee->id,
                '', // gambar dimasukkan kemudian
                $this->dashIfEmpty($employee->name),
                $this->dashIfEmpty(optional($employee->department)->name),
                $this->dashIfEmpty($employee->email),
                $this->dashIfEmpty($employee->phone),
                $this->dashIfEmpty(strip_tags($employee->full_address)),
                ucfirst($this->dashIfEmpty($employee->status)),
                optional($employee->created_at)->format('Y-m-d H:i'),
                optional($employee->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $sheet->getStyle('A2:K2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $highestRow = $sheet->getHighestRow();
        foreach (range(3, $highestRow) as $row) {
            foreach (range('A', 'K') as $col) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
            }
            // tinggi default untuk baris foto nanti akan disesuaikan
        }

        // Ukuran kolom
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(8);
        $sheet->getColumnDimension('C')->setWidth(14); // cukup untuk gambar
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);
        $sheet->getColumnDimension('H')->setWidth(40);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setAutoSize(true);
        $sheet->getColumnDimension('K')->setAutoSize(true);

        return [];
    }

    public function registerEvents(): array
{
    return [
        AfterSheet::class => function (AfterSheet $event) {
            if (!$this->employeesForDrawing || $this->employeesForDrawing->isEmpty()) {
                return;
            }

            $sheet = $event->sheet->getDelegate();
            $row = 3;

            // Lebar kolom C (Foto) dalam satuan pixel (≈ lebar Excel * 7)
            $colWidth = 14 * 7; // 14 = width from styles(), 1 Excel width ≈ 7 pixel

            foreach ($this->employeesForDrawing as $employee) {
                if (!empty($employee->photo)) {
                    $path = null;

                    if (Storage::exists($employee->photo)) {
                        $path = Storage::path($employee->photo);
                    } elseif (Storage::disk('public')->exists($employee->photo)) {
                        $path = Storage::disk('public')->path($employee->photo);
                    }

                    if ($path && is_file($path)) {
                        list($width, $height) = getimagesize($path);

                        // Skala agar tidak melebihi 60px (proporsional)
                        $maxSize = 60;
                        $scale = min($maxSize / $width, $maxSize / $height, 1);
                        $newWidth = $width * $scale;
                        $newHeight = $height * $scale;

                        // Hitung tinggi baris (sedikit lebih besar dari gambar)
                        $rowHeight = $newHeight + 10;
                        $sheet->getRowDimension($row)->setRowHeight($rowHeight);

                        // Hitung posisi tengah (center)
                        $offsetX = max(0, ($colWidth - $newWidth) / 2);
                        $offsetY = max(0, ($rowHeight - $newHeight) / 2);

                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setHeight($newHeight);
                        $drawing->setWidth($newWidth);
                        $drawing->setCoordinates("C{$row}");
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY($offsetY);
                        $drawing->setWorksheet($sheet);
                    } else {
                        $sheet->setCellValue("C{$row}", '-');
                        $sheet->getStyle("C{$row}")
                              ->getAlignment()
                              ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                              ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    }
                } else {
                    $sheet->setCellValue("C{$row}", '-');
                    $sheet->getStyle("C{$row}")
                          ->getAlignment()
                          ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                          ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                }

                $row++;
            }
        },
    ];
}

}
