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

class ProductReturnExport implements FromArray, WithStyles, WithEvents
{
    protected ProductReturn $return;

    /** @var array<int, string> */
    protected array $productImagePaths = [];
    /** @var array<int, string> */
    protected array $deliveryImagePaths = [];

    public function __construct(ProductReturn $return)
    {
        $this->return = $return;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    /**
     * Ambil maksimal 3 path gambar dari field gambar
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

    public function array(): array
    {
        // ===== HEADER (disamakan gaya dengan GaransiExport) =====
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
            'Status Pengajuan',
            'Status Produk',
            'Status Return',
            'Batas Hold',
            'Alasan Hold',
            'Foto Barang',
            'Bukti Pengiriman', // kolom gambar terakhir
        ];

        $rows = [
            array_fill(0, count($headers), ''),
            $headers,
        ];

        // judul di tengah baris pertama
        $rows[0][(int) floor(count($headers) / 2)] = 'PRODUCT RETURN';

        // kumpulkan path gambar untuk 1 return
        $this->productImagePaths  = $this->parseImagePaths($this->return->image);
        $this->deliveryImagePaths = $this->parseImagePaths($this->return->delivery_images);

        $no = 1;

        // ===== GROUPING PRODUK (Brand+Category+Product+Warna+Barcode) =====
        $groupedItems = collect($this->return->productsWithDetails() ?? [])
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

        // ===== DATA BARIS DETAIL PRODUK (SUDAH DI-GROUP) =====
        foreach ($groupedItems as $item) {
            $brand    = $item['brand_name']    ?? '-';
            $category = $item['category_name'] ?? '-';
            $product  = $item['product_name']  ?? '-';
            $color    = $item['color']         ?? '-';
            $barcode  = $item['barcode']       ?? '-';
            $qty      = (int) ($item['quantity'] ?? 0);

            $rows[] = [
                $no++,
                $this->dashIfEmpty($this->return->no_return),
                $this->dashIfEmpty(optional($this->return->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($this->return->customer->name ?? '-'),

                $this->dashIfEmpty($barcode),
                $this->dashIfEmpty($brand),
                $this->dashIfEmpty($category),
                $this->dashIfEmpty($product),
                $this->dashIfEmpty($color),

                $this->dashIfEmpty($qty),
                $this->dashIfEmpty($this->return->reason ?? '-'),

                'Rp ' . number_format((int) $this->return->amount, 0, ',', '.'),

                $this->dashIfEmpty($this->return->employee->name ?? '-'),
                $this->dashIfEmpty($this->return->department->name ?? '-'),

                // Status Pengajuan
                $this->dashIfEmpty(match ($this->return->status_pengajuan) {
                    'pending'  => 'Pending',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default    => $this->return->status_pengajuan
                        ? ucfirst((string) $this->return->status_pengajuan)
                        : '-',
                }),
                // Status Produk
                $this->dashIfEmpty(match ($this->return->status_product) {
                    'pending'     => 'Pending',
                    'ready_stock' => 'Ready Stock',
                    'sold_out'    => 'Sold Out',
                    'rejected'    => 'Ditolak',
                    default       => $this->return->status_product
                        ? ucfirst((string) $this->return->status_product)
                        : '-',
                }),
                // Status Return
                $this->dashIfEmpty(match ($this->return->status_return) {
                    'pending'    => 'Pending',
                    'confirmed'  => 'Confirmed',
                    'processing' => 'Processing',
                    'on_hold'    => 'On Hold',
                    'delivered'  => 'Delivered',
                    'completed'  => 'Completed',
                    'cancelled'  => 'Cancelled',
                    'rejected'   => 'Ditolak',
                    default      => $this->return->status_return
                        ? ucfirst((string) $this->return->status_return)
                        : '-',
                }),

                $this->dashIfEmpty(optional($this->return->on_hold_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($this->return->on_hold_comment ?? '-'),

                empty($this->productImagePaths)  ? '-' : '',
                empty($this->deliveryImagePaths) ? '-' : '',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet        = $event->sheet->getDelegate();
                $lastCol      = $sheet->getHighestColumn();
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);

                // Kolom: Foto Barang = kolom ke-2 dari belakang, Bukti Pengiriman = kolom terakhir
                $productColIndex  = $lastColIndex - 1;
                $deliveryColIndex = $lastColIndex;

                $productCol  = Coordinate::stringFromColumnIndex($productColIndex);
                $deliveryCol = Coordinate::stringFromColumnIndex($deliveryColIndex);

                // data mulai baris 3
                $startRow = 3;
                $dataRows = max(0, count($this->return->productsWithDetails()));
                if ($dataRows === 0) {
                    return;
                }
                $endRow = $startRow + $dataRows - 1;

                // set lebar dan tinggi baris
                $sheet->getColumnDimension($productCol)->setWidth(40);
                $sheet->getColumnDimension($deliveryCol)->setWidth(40);

                for ($r = $startRow; $r <= $endRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(65);
                }

                // tanam foto barang di baris pertama data
                if (!empty($this->productImagePaths)) {
                    $offsetX = 5;
                    foreach (array_slice($this->productImagePaths, 0, 3) as $path) {
                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setWorksheet($sheet);
                        $drawing->setCoordinates($productCol . $startRow);
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setHeight(55);
                        $offsetX += 60;
                    }
                } else {
                    $sheet->setCellValue($productCol . $startRow, '-');
                }

                // tanam bukti pengiriman di baris pertama data
                if (!empty($this->deliveryImagePaths)) {
                    $offsetX = 5;
                    foreach (array_slice($this->deliveryImagePaths, 0, 3) as $path) {
                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setWorksheet($sheet);
                        $drawing->setCoordinates($deliveryCol . $startRow);
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setHeight(55);
                        $offsetX += 60;
                    }
                } else {
                    $sheet->setCellValue($deliveryCol . $startRow, '-');
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol    = $sheet->getHighestColumn();
        $lastColIdx = Coordinate::columnIndexFromString($lastCol);
        $highestRow = $sheet->getHighestRow();

        // Title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'PRODUCT RETURN');
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header
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

        // Data rows
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

        // Autosize semua kolom kecuali 2 kolom gambar (biar width 40 tetap)
        $productColIndex  = $lastColIdx - 1;
        $deliveryColIndex = $lastColIdx;

        for ($i = 1; $i <= $lastColIdx; $i++) {
            if ($i === $productColIndex || $i === $deliveryColIndex) {
                continue;
            }
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
