<?php

namespace App\Exports;

use App\Models\Garansi;
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

class GaransiExport implements FromArray, WithStyles, WithEvents
{
    protected Garansi $garansi;

    /** @var array<int, string> */
    protected array $productImagePaths = [];   // foto barang
    /** @var array<int, string> */
    protected array $deliveryImagePaths = [];  // bukti pengiriman

    public function __construct(Garansi $garansi)
    {
        $this->garansi = $garansi;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    /**
     * Format alamat kalau nanti dibutuhkan lagi
     */
    protected function formatAddress($address): string
    {
        if (is_array($address)) {
            $parts = [
                $address['detail_alamat'] ?? null,
                $address['kelurahan']     ?? null,
                $address['kecamatan']     ?? null,
                $address['kota_kab']      ?? null,
                $address['provinsi']      ?? null,
                $address['kode_pos']      ?? null,
            ];
            $txt = implode(', ', array_filter($parts, fn ($v) => $v && $v !== '-'));
            return $txt !== '' ? $txt : '-';
        }

        return $this->dashIfEmpty($address);
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
        // ===== HEADER (disamain gaya dengan OrderExport) =====
        $headers = [
            'No.',
            'No Garansi',
            'Tanggal Dibuat',
            'Tanggal Pembelian',
            'Tanggal Klaim',
            'Customer',
            'Barcode',
            'Brand',
            'Category',
            'Product',
            'Warna',
            'Pcs/item',
            'Alasan Klaim',
            'Karyawan',
            'Department',
            'Kategori Customer',
            'Status Pengajuan',
            'Status Produk',
            'Status Garansi',
            'Batas Hold',
            'Alasan Hold',
            'Foto Barang',
            'Bukti Pengiriman', // 2 kolom gambar terakhir
        ];

        $rows = [
            array_fill(0, count($headers), ''),
            $headers,
        ];

        // judul di tengah baris pertama
        $rows[0][(int) floor(count($headers) / 2)] = 'GARANSI';

        // simpan path gambar (untuk 1 garansi)
        $this->productImagePaths  = $this->parseImagePaths($this->garansi->image);
        $this->deliveryImagePaths = $this->parseImagePaths($this->garansi->delivery_images);

        $no = 1;

        // ===== GROUPING PRODUK (Brand+Category+Product+Warna+Barcode) =====
        $groupedItems = collect($this->garansi->productsWithDetails() ?? [])
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
                $this->dashIfEmpty($this->garansi->no_garansi),
                $this->dashIfEmpty(optional($this->garansi->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty(optional($this->garansi->purchase_date)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty(optional($this->garansi->claim_date)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($this->garansi->customer->name ?? '-'),

                $this->dashIfEmpty($barcode),
                $this->dashIfEmpty($brand),
                $this->dashIfEmpty($category),
                $this->dashIfEmpty($product),
                $this->dashIfEmpty($color),

                $this->dashIfEmpty($qty),
                $this->dashIfEmpty($this->garansi->reason ?? '-'),

                $this->dashIfEmpty($this->garansi->employee->name ?? '-'),
                $this->dashIfEmpty($this->garansi->department->name ?? '-'),
                $this->dashIfEmpty($this->garansi->customerCategory->name ?? '-'),

                $this->dashIfEmpty($this->garansi->status_pengajuan ?? $this->garansi->status ?? '-'),
                $this->dashIfEmpty($this->garansi->status_product ?? '-'),
                $this->dashIfEmpty($this->garansi->status_garansi ?? '-'),

                $this->dashIfEmpty(optional($this->garansi->on_hold_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($this->garansi->on_hold_comment ?? '-'),

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

                // data mulai baris 3
                $startRow = 3;
                $dataRows = max(0, count($this->garansi->productsWithDetails()));
                $endRow   = $startRow + $dataRows - 1;

                if ($dataRows === 0) {
                    return;
                }

                // Kolom: Foto Barang = kolom ke-2 dari belakang, Bukti Pengiriman = kolom terakhir
                $productColIndex  = $lastColIndex - 1;
                $deliveryColIndex = $lastColIndex;

                $productCol  = Coordinate::stringFromColumnIndex($productColIndex);
                $deliveryCol = Coordinate::stringFromColumnIndex($deliveryColIndex);

                // set lebar kolom gambar
                $sheet->getColumnDimension($productCol)->setWidth(40);
                $sheet->getColumnDimension($deliveryCol)->setWidth(40);

                // set tinggi baris data
                for ($row = $startRow; $row <= $endRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(65);
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

        // Judul
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'GARANSI');
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header row 2
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
