<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\Storage;

class CategoryExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** Untuk proses penyisipan gambar di AfterSheet */
    protected $categoriesForDrawing;

    /** Preferensi tampilan kolom gambar */
    protected int $photoColumnWidthExcel = 14;  // lebar kolom C (unit Excel)
    protected int $pxPerExcelUnit       = 7;    // ~7px per 1 unit Excel
    protected int $photoPaddingPx       = 10;   // padding horizontal dalam kolom foto (px)
    protected int $maxPhotoHeightPx     = 70;   // tinggi maksimum foto (px)

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    public function array(): array
    {
        $query = Category::with(['brand', 'products']);

        if (!empty($this->filters['brand_id'])) {
            $query->where('brand_id', $this->filters['brand_id']);
        }

        $categories = $query
            ->orderBy('brand_id')
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        // simpan utk AfterSheet (gambar)
        $this->categoriesForDrawing = $categories;

        // Judul + header (10 kolom total: A..J)
        $rows = [
            ['', '', '', '', 'DATA KATEGORI', '', '', '', '', ''],
            [
                'No.',
                'ID',
                'Gambar',
                'Brand',
                'Nama Kategori',
                'Deskripsi',
                'Jumlah Pengguna',
                'Status',
                'Created At',
                'Updated At',
            ],
        ];

        $no = 1;
        foreach ($categories as $category) {
            $rows[] = [
                $no++,
                $category->id,
                '', // gambar isi via AfterSheet
                $this->dashIfEmpty(optional($category->brand)->name),
                $this->dashIfEmpty($category->name),
                $this->dashIfEmpty($category->deskripsi),
                $category->products->count() ?: '-',
                $this->dashIfEmpty(ucfirst($category->status)),
                optional($category->created_at)->format('Y-m-d H:i'),
                optional($category->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Merge judul & center
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

        // Body (border + semua rata tengah)
        $highestRow = $sheet->getHighestRow();
        foreach (range(3, $highestRow) as $row) {
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

        // Lebar kolom (C = gambar)
        $sheet->getColumnDimension('A')->setWidth(6);                         // No.
        $sheet->getColumnDimension('B')->setWidth(8);                         // ID
        $sheet->getColumnDimension('C')->setWidth($this->photoColumnWidthExcel); // Gambar
        $sheet->getColumnDimension('D')->setWidth(22);                        // Brand
        $sheet->getColumnDimension('E')->setWidth(25);                        // Nama Kategori
        $sheet->getColumnDimension('F')->setWidth(40);                        // Deskripsi
        $sheet->getColumnDimension('G')->setWidth(18);                        // Jumlah Pengguna
        $sheet->getColumnDimension('H')->setWidth(12);                        // Status
        $sheet->getColumnDimension('I')->setWidth(20);                        // Created
        $sheet->getColumnDimension('J')->setWidth(20);                        // Updated

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (empty($this->categoriesForDrawing) || $this->categoriesForDrawing->isEmpty()) {
                    return;
                }

                $sheet      = $event->sheet->getDelegate();
                $row        = 3;
                $colWidthPx = $this->photoColumnWidthExcel * $this->pxPerExcelUnit; // konversi unit Excel -> px

                foreach ($this->categoriesForDrawing as $category) {
                    $imgPath = $category->image ?? null;

                    if ($imgPath) {
                        // Tentukan path absolut file gambar
                        $absPath = null;
                        if (Storage::exists($imgPath)) {
                            $absPath = Storage::path($imgPath);
                        } elseif (Storage::disk('public')->exists($imgPath)) {
                            $absPath = Storage::disk('public')->path($imgPath);
                        }

                        if ($absPath && is_file($absPath)) {
                            [$w, $h] = @getimagesize($absPath) ?: [0, 0];

                            if ($w > 0 && $h > 0) {
                                // Hitung batas skala
                                $maxW = max(1, $colWidthPx - $this->photoPaddingPx);
                                $maxH = $this->maxPhotoHeightPx;
                                $scale = min($maxW / $w, $maxH / $h, 1);

                                $newW = $w * $scale;
                                $newH = $h * $scale;

                                // Tinggi baris menyesuaikan tinggi gambar (+ padding tipis)
                                $rowHeightPx = (int) max($newH + 8, 20);
                                $sheet->getRowDimension($row)->setRowHeight($rowHeightPx);

                                // Pusatkan gambar dalam sel C{row}
                                $offsetX = (int) max(0, ($colWidthPx - $newW) / 2);
                                $offsetY = (int) max(0, ($rowHeightPx - $newH) / 2);

                                $drawing = new Drawing();
                                $drawing->setPath($absPath);
                                $drawing->setWidth($newW);
                                $drawing->setHeight($newH);
                                $drawing->setCoordinates("C{$row}");
                                $drawing->setOffsetX($offsetX);
                                $drawing->setOffsetY($offsetY);
                                $drawing->setWorksheet($sheet);
                            } else {
                                // gagal baca dimensi
                                $sheet->setCellValue("C{$row}", '-');
                            }
                        } else {
                            // file tidak ditemukan
                            $sheet->setCellValue("C{$row}", '-');
                        }
                    } else {
                        // tidak ada gambar
                        $sheet->setCellValue("C{$row}", '-');
                    }

                    $row++;
                }
            },
        ];
    }
}
