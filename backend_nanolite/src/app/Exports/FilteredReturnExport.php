<?php

namespace App\Exports;

use App\Models\ProductReturn;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class FilteredReturnExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** @var array<int, array<int,string>> rowIndex => [paths...] */
    protected array $productImageMap  = [];
    /** @var array<int, array<int,string>> rowIndex => [paths...] */
    protected array $deliveryImageMap = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    /**
     * Ambil max 3 path gambar dari field images
     */
    protected function parseImagePaths($images): array
    {
        if (is_string($images) && str_starts_with($images, '[')) {
            $images = json_decode($images, true);
        }

        $arr = [];
        if (is_array($images)) {
            $arr = $images;
        } elseif (is_string($images) && $images !== '') {
            $arr = [$images];
        }

        $paths = [];
        foreach ($arr as $p) {
            $p   = preg_replace('#^/?storage/#', '', $p);
            $abs = storage_path('app/public/' . ltrim($p, '/'));
            if (is_file($abs)) {
                $paths[] = $abs;
            }
            if (count($paths) >= 3) {
                break;
            }
        }

        return $paths;
    }

    /**
     * Filter manual by brand/category/product dari productsWithDetails()
     */
    protected function applyManualFilters($returns)
    {
        if (!empty($this->filters['brand_id'])) {
            $returns = $returns->filter(function ($r) {
                foreach ($r->productsWithDetails() as $i) {
                    if (($i['brand_id'] ?? null) == $this->filters['brand_id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        if (!empty($this->filters['category_id'])) {
            $returns = $returns->filter(function ($r) {
                foreach ($r->productsWithDetails() as $i) {
                    if (($i['category_id'] ?? null) == $this->filters['category_id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        if (!empty($this->filters['product_id'])) {
            $returns = $returns->filter(function ($r) {
                foreach ($r->productsWithDetails() as $i) {
                    if (($i['product_id'] ?? null) == $this->filters['product_id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $returns;
    }

    public function array(): array
    {
        $q = ProductReturn::with([
            'customer.customerCategory',
            'employee',
            'department',
            'category',
        ])->orderBy('created_at', 'asc');

        // ===== FILTER DARI FORM EXPORT (disamain dengan FilteredGaransiExport) =====
        if (!empty($this->filters['department_id'])) {
            $q->where('department_id', $this->filters['department_id']);
        }
        if (!empty($this->filters['customer_id'])) {
            $q->where('customer_id', $this->filters['customer_id']);
        }
        if (!empty($this->filters['employee_id'])) {
            $q->where('employee_id', $this->filters['employee_id']);
        }
        if (!empty($this->filters['customer_categories_id'])) {
            $q->where('customer_categories_id', $this->filters['customer_categories_id']);
        }

        // filter status (menyesuaikan field di ProductReturn)
        if (!empty($this->filters['status_pengajuan'])) {
            $q->where('status_pengajuan', $this->filters['status_pengajuan']);
        }
        if (!empty($this->filters['status_product'])) {
            $q->where('status_product', $this->filters['status_product']);
        }
        if (!empty($this->filters['status_return'])) {
            $q->where('status_return', $this->filters['status_return']);
        }

        // filter tanggal dibuat (created_at)
        if (!empty($this->filters['created_from'])) {
            $q->whereDate('created_at', '>=', $this->filters['created_from']);
        }
        if (!empty($this->filters['created_until'])) {
            $q->whereDate('created_at', '<=', $this->filters['created_until']);
        }

        $returns = $this->applyManualFilters($q->get());

        // ===== HEADER (gaya mirip FilteredGaransiExport, tapi menyesuaikan return) =====
        $headers = [
            'No.',
            'No Return',
            'Tanggal Dibuat',
            'Customer',
            'Barcode',
            'Brand',
            'Category',
            'Product',
            'Warna',
            'Pcs/item',
            'Alasan Return',
            'Nominal',
            'Karyawan',
            'Department',
            'Kategori Customer',
            'Status Pengajuan',
            'Status Produk',
            'Status Return',
            'Batas Hold',
            'Alasan Hold',
            'Foto Barang',
            'Bukti Pengiriman',
        ];

        $rows = [
            array_fill(0, count($headers), ''),
            $headers,
        ];
        $rows[0][(int) floor(count($headers) / 2)] = 'PRODUCT RETURN';

        $no         = 1;
        $startRow   = 3;
        $currentRow = $startRow;

        foreach ($returns as $r) {
            $items = $r->productsWithDetails() ?? [];

            // group per kombinasi Brand+Category+Product+Warna+Barcode
            $groupedItems = collect($items)
                ->groupBy(function ($item) {
                    return implode('|', [
                        $item['brand_name']    ?? '',
                        $item['category_name'] ?? '',
                        $item['product_name']  ?? '',
                        $item['color']         ?? '',
                        $item['barcode']       ?? '',
                    ]);
                })
                ->map(function ($group) {
                    $first = $group->first();

                    $qtyTotal = collect($group)->sum(function ($i) {
                        return (int) ($i['quantity'] ?? 0);
                    });

                    $first['quantity'] = $qtyTotal;

                    return $first;
                })
                ->values();

            $brandList    = [];
            $categoryList = [];
            $productList  = [];
            $colorList    = [];
            $barcodeList  = [];
            $qtyList      = [];

            foreach ($groupedItems as $item) {
                $brand    = $item['brand_name']    ?? '-';
                $category = $item['category_name'] ?? '-';
                $product  = $item['product_name']  ?? '-';
                $color    = $item['color']         ?? '-';
                $barcode  = $item['barcode']       ?? '-';
                $qty      = (int) ($item['quantity'] ?? 0);

                $brandList[]    = $brand;
                $categoryList[] = $category;
                $productList[]  = $product;
                $colorList[]    = $color;
                $barcodeList[]  = $barcode;
                $qtyList[]      = (string) $qty;
            }

            // simpan image paths untuk baris ini
            $this->productImageMap[$currentRow]  = $this->parseImagePaths($r->image);
            $this->deliveryImageMap[$currentRow] = $this->parseImagePaths($r->delivery_images);

            $rows[] = [
                $no++,
                $this->dashIfEmpty($r->no_return),
                $this->dashIfEmpty(optional($r->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($r->customer->name ?? '-'),

                // multiline item detail (mirip FilteredGaransiExport)
                empty($barcodeList)
                    ? '-'
                    : implode("\n", array_map(fn ($v) => $this->dashIfEmpty($v), $barcodeList)),
                empty($brandList)
                    ? '-'
                    : implode("\n", array_map(fn ($v) => $this->dashIfEmpty($v), $brandList)),
                empty($categoryList)
                    ? '-'
                    : implode("\n", array_map(fn ($v) => $this->dashIfEmpty($v), $categoryList)),
                empty($productList)
                    ? '-'
                    : implode("\n", array_map(fn ($v) => $this->dashIfEmpty($v), $productList)),
                empty($colorList)
                    ? '-'
                    : implode("\n", array_map(fn ($v) => $this->dashIfEmpty($v), $colorList)),

                empty($qtyList)
                    ? '-'
                    : implode("\n", $qtyList),

                $this->dashIfEmpty($r->reason ?? '-'),

                'Rp ' . number_format((int) $r->amount, 0, ',', '.'),

                $this->dashIfEmpty($r->employee->name ?? '-'),
                $this->dashIfEmpty($r->department->name ?? '-'),
                $this->dashIfEmpty($r->customer?->customerCategory->name ?? '-'),

                $this->dashIfEmpty($r->status_pengajuan ?? $r->status ?? '-'),
                $this->dashIfEmpty($r->status_product ?? '-'),
                $this->dashIfEmpty($r->status_return ?? '-'),

                $this->dashIfEmpty(optional($r->on_hold_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($r->on_hold_comment ?? '-'),

                empty($this->productImageMap[$currentRow])  ? '-' : '',
                empty($this->deliveryImageMap[$currentRow]) ? '-' : '',
            ];

            $currentRow++;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet       = $event->sheet->getDelegate();
                $lastCol     = $sheet->getHighestColumn();
                $lastColIdx  = Coordinate::columnIndexFromString($lastCol);

                // Kolom gambar: Foto Barang = kolom ke-2 dari belakang, Bukti Pengiriman = kolom terakhir
                $fotoColIdx     = $lastColIdx - 1;
                $deliveryColIdx = $lastColIdx;

                $fotoCol     = Coordinate::stringFromColumnIndex($fotoColIdx);
                $deliveryCol = Coordinate::stringFromColumnIndex($deliveryColIdx);

                // set lebar kolom gambar
                $sheet->getColumnDimension($fotoCol)->setWidth(40);
                $sheet->getColumnDimension($deliveryCol)->setWidth(40);

                // tanam foto barang
                foreach ($this->productImageMap as $row => $paths) {
                    $sheet->getRowDimension($row)->setRowHeight(65);

                    if (empty($paths)) {
                        $sheet->setCellValue($fotoCol . $row, '-');
                        continue;
                    }

                    $offsetX = 5;
                    foreach (array_slice($paths, 0, 3) as $path) {
                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setWorksheet($sheet);
                        $drawing->setCoordinates($fotoCol . $row);
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setHeight(55);
                        $offsetX += 60;
                    }
                }

                // tanam bukti pengiriman
                foreach ($this->deliveryImageMap as $row => $paths) {
                    $sheet->getRowDimension($row)->setRowHeight(65);

                    if (empty($paths)) {
                        $sheet->setCellValue($deliveryCol . $row, '-');
                        continue;
                    }

                    $offsetX = 5;
                    foreach (array_slice($paths, 0, 3) as $path) {
                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setWorksheet($sheet);
                        $drawing->setCoordinates($deliveryCol . $row);
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setHeight(55);
                        $offsetX += 60;
                    }
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol     = $sheet->getHighestColumn();
        $lastColIdx  = Coordinate::columnIndexFromString($lastCol);
        $highestRow  = $sheet->getHighestRow();

        // judul
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'PRODUCT RETURN');
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // header
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F0F0'],
            ],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // data
        for ($row = 3; $row <= $highestRow; $row++) {
            for ($i = 1; $i <= $lastColIdx; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_TOP,
                        'wrapText'   => true,
                    ],
                ]);
            }
        }

        // autosize kecuali 2 kolom gambar (biar width 40 tetap)
        $fotoColIdx     = $lastColIdx - 1;
        $deliveryColIdx = $lastColIdx;

        for ($i = 1; $i <= $lastColIdx; $i++) {
            if ($i === $fotoColIdx || $i === $deliveryColIdx) {
                continue;
            }
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
