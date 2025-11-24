<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use App\Models\Brand;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BrandExport implements FromArray, WithStyles, WithEvents
{
    protected Collection $brands;
    protected ?Collection $brandsForDrawing = null;

    public function __construct(Collection $brands)
    {
        $this->brands = $brands;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    protected function safeCount($brand, string $relation): int|string
    {
        if (isset($brand->{$relation . '_count'})) {
            return (int) $brand->{$relation . '_count'} ?: '-';
        }
        if ($brand->relationLoaded($relation)) {
            return $brand->{$relation}->count() ?: '-';
        }
        return $brand->{$relation}()->count() ?: '-';
    }

    public function array(): array
    {
        $this->brandsForDrawing = $this->brands;

        $rows = [
            ['', '', '', '', 'DATA BRAND', '', '', '', '', ''],
            [
                'No.',
                'ID',
                'Gambar',
                'Nama Brand',
                'Deskripsi',
                'Jumlah Pengguna di Kategori',
                'Jumlah Pengguna di Produk',
                'Status',
                'Dibuat Pada',
                'Diupdate Pada',
            ],
        ];

        $no = 1;
        foreach ($this->brands as $brand) {
            $rows[] = [
                $no++,
                $brand->id,
                '',
                $this->dashIfEmpty($brand->name),
                $this->dashIfEmpty($brand->deskripsi),
                $this->safeCount($brand, 'categories'),
                $this->safeCount($brand, 'products'),
                ucfirst($this->dashIfEmpty($brand->status)),
                optional($brand->created_at)->format('Y-m-d H:i'),
                optional($brand->updated_at)->format('Y-m-d H:i'),
            ];
        }

        $rows[] = array_fill(0, 10, '');
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Merge judul & center text
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header
        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F0F0'],
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data body
        $lastRow = 2 + $this->brands->count();
        foreach (range(3, $lastRow) as $row) {
            foreach (range('A', 'J') as $col) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);
            }
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(8);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(20);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (!$this->brandsForDrawing || $this->brandsForDrawing->isEmpty()) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $row = 3;
                $colWidthPx = 14 * 7;
                $maxSize = 60;

                foreach ($this->brandsForDrawing as $brand) {
                    $imgPath = $brand->image ?? null;

                    if ($imgPath) {
                        $absPath = null;

                        if (Storage::exists($imgPath)) {
                            $absPath = Storage::path($imgPath);
                        } elseif (Storage::disk('public')->exists($imgPath)) {
                            $absPath = Storage::disk('public')->path($imgPath);
                        }

                        if ($absPath && is_file($absPath)) {
                            [$w, $h] = @getimagesize($absPath) ?: [0, 0];

                            if ($w > 0 && $h > 0) {
                                $scale = min($maxSize / $w, $maxSize / $h, 1);
                                $newW = $w * $scale;
                                $newH = $h * $scale;

                                $rowHeightPx = $newH + 10;
                                $sheet->getRowDimension($row)->setRowHeight($rowHeightPx);

                                $offsetX = max(0, ($colWidthPx - $newW) / 2);
                                $offsetY = max(0, ($rowHeightPx - $newH) / 2);

                                $drawing = new Drawing();
                                $drawing->setPath($absPath);
                                $drawing->setWidth($newW);
                                $drawing->setHeight($newH);
                                $drawing->setCoordinates("C{$row}");
                                $drawing->setOffsetX($offsetX);
                                $drawing->setOffsetY($offsetY);
                                $drawing->setWorksheet($sheet);
                            } else {
                                $sheet->setCellValue("C{$row}", '-');
                            }
                        } else {
                            $sheet->setCellValue("C{$row}", '-');
                        }
                    } else {
                        $sheet->setCellValue("C{$row}", '-');
                    }

                    // Semua sel di baris ini center juga
                    foreach (range('A', 'J') as $col) {
                        $sheet->getStyle("{$col}{$row}")
                              ->getAlignment()
                              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                              ->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    $row++;
                }
            },
        ];
    }
}
