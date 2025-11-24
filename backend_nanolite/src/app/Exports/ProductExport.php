<?php

namespace App\Exports;

use App\Models\Product;
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

class ProductExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** Simpan untuk proses penyisipan gambar di AfterSheet */
    protected $productsForDrawing;

    /** Preferensi kolom gambar */
    protected int $photoColumnWidthExcel = 16;
    protected int $pxPerExcelUnit       = 7;
    protected int $photoPaddingPx       = 10;
    protected int $maxPhotoHeightPx     = 80;

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
        $query = Product::with(['brand', 'category']);

        if (!empty($this->filters['brand_id'])) {
            $query->where('brand_id', $this->filters['brand_id']);
        }

        if (!empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        $products = $query
            ->orderBy('brand_id')
            ->orderBy('category_id')
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        $this->productsForDrawing = $products;

        // Judul + header
        $rows = [
            ['', '', '', '', '', '', '', 'DATA PRODUK', '', '', '', ''],
            [
                'No.',
                'ID',
                'Gambar',
                'Brand',
                'Kategori',
                'Nama Produk',
                'Warna',
                'Harga',
                'Deskripsi',
                'Status',
                'Created At',
                'Updated At',
            ],
        ];

        $no = 1;
        foreach ($products as $product) {
            $rows[] = [
                $no++,
                $product->id,
                '', // gambar disisipkan via AfterSheet di kolom C
                $this->dashIfEmpty(optional($product->brand)->name),
                $this->dashIfEmpty(optional($product->category)->name),
                $this->dashIfEmpty($product->name),
                is_array($product->colors) ? implode(', ', $product->colors) : $this->dashIfEmpty($product->colors),
                $product->price !== null ? ('Rp ' . number_format($product->price, 0, ',', '.')) : '-',
                $this->dashIfEmpty($product->description),
                $this->dashIfEmpty(ucfirst($product->status)),
                optional($product->created_at)->format('Y-m-d H:i'),
                optional($product->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Merge judul agar tepat di tengah
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header (A2:L2)
        $sheet->getStyle('A2:L2')->applyFromArray([
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

        // Body
        $highestRow = $sheet->getHighestRow();
        foreach (range(3, $highestRow) as $row) {
            foreach (range('A', 'L') as $col) {
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
        $sheet->getColumnDimension('C')->setWidth($this->photoColumnWidthExcel); // gambar
        $sheet->getColumnDimension('D')->setWidth(18); // brand
        $sheet->getColumnDimension('E')->setWidth(18); // kategori
        $sheet->getColumnDimension('F')->setWidth(28); // nama produk
        $sheet->getColumnDimension('G')->setWidth(18); // warna
        $sheet->getColumnDimension('H')->setWidth(16); // harga
        $sheet->getColumnDimension('I')->setWidth(40); // deskripsi
        $sheet->getColumnDimension('J')->setWidth(12); // status
        $sheet->getColumnDimension('K')->setWidth(20); // created at
        $sheet->getColumnDimension('L')->setWidth(20); // updated at

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (empty($this->productsForDrawing) || $this->productsForDrawing->isEmpty()) {
                    return;
                }

                $sheet      = $event->sheet->getDelegate();
                $row        = 3;
                $colWidthPx = $this->photoColumnWidthExcel * $this->pxPerExcelUnit;

                foreach ($this->productsForDrawing as $product) {
                    $imgPath = $product->image ?? null;

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
                                $maxW = max(1, $colWidthPx - $this->photoPaddingPx);
                                $maxH = $this->maxPhotoHeightPx;
                                $scale = min($maxW / $w, $maxH / $h, 1);
                                $newW = $w * $scale;
                                $newH = $h * $scale;

                                $rowHeightPx = (int) max($newH + 8, 20);
                                $sheet->getRowDimension($row)->setRowHeight($rowHeightPx);

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
                                $sheet->setCellValue("C{$row}", '-');
                            }
                        } else {
                            $sheet->setCellValue("C{$row}", '-');
                        }
                    } else {
                        $sheet->setCellValue("C{$row}", '-');
                    }

                    $row++;
                }
            },
        ];
    }
}
