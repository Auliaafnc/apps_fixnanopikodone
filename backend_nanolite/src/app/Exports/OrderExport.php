<?php

namespace App\Exports;

use App\Models\Order;
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

class OrderExport implements FromArray, WithStyles, WithEvents
{
    protected Order $order;

    /** @var array<int,string> */
    protected array $imagePaths = [];

    /** baris terakhir data detail (utk styling) */
    protected int $dataEndRow = 0;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    protected function mapStatusPembayaran(?string $state): string
    {
        $state = $state ?? '';
        return match ($state) {
            'belum bayar' => 'Belum Bayar',
            'sudah bayar' => 'Sudah Bayar',
            'belum lunas' => 'Belum Lunas',
            'sudah lunas' => 'Sudah Lunas',
            default       => $state === '' ? '-' : ucfirst($state),
        };
    }

    protected function mapStatusPengajuan(?string $state): string
    {
        $state = $state ?? '';
        return match ($state) {
            'pending'  => 'Pending',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default    => $state === '' ? '-' : ucfirst($state),
        };
    }

    protected function mapStatusProduct(?string $state): string
    {
        $state = $state ?? '';
        return match ($state) {
            'pending'     => 'Pending',
            'ready_stock' => 'Ready Stock',
            'sold_out'    => 'Sold Out',
            'rejected'    => 'Ditolak',
            default       => $state === '' ? '-' : ucfirst($state),
        };
    }

    protected function mapStatusOrder(?string $state): string
    {
        $state = $state ?? '';
        return match ($state) {
            'pending'    => 'Pending',
            'confirmed'  => 'Confirmed',
            'processing' => 'Processing',
            'on_hold'    => 'On Hold',
            'delivered'  => 'Delivered',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
            'rejected'   => 'Ditolak',
            default      => $state === '' ? '-' : ucfirst($state),
        };
    }

    /**
     * Ambil maksimal 3 path gambar dari delivery_images
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
            // buang prefix "storage/" kalau ada
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
        // ===== HEADER =====
        $headers = [
            'No.',
            'No Order',
            'Tanggal Dibuat',
            'Tanggal Diupdate',
            'Customer',
            'Barcode',
            'Brand',
            'Category',
            'Product',
            'Warna',
            'Pcs/item',
            'Unit Price',
            'Disc%',
            'Total Akhir',
            'Metode Pembayaran',
            'Batas Tempo',
            'Karyawan',
            'Department',
            'Customer Program',
            'Kategori Customer',
            'Status Pembayaran',
            'Status Pengajuan',
            'Status Produk',
            'Status Order',
            'Batas Hold',
            'Alasan Hold',
            'Bukti Pengiriman',
        ];

        $rows = [
            array_fill(0, count($headers), ''),
            $headers,
        ];

        // judul di tengah baris pertama
        $rows[0][(int) floor(count($headers) / 2)] = 'SALES ORDER';

        // simpan path gambar delivery
        $this->imagePaths = $this->parseImagePaths($this->order->delivery_images);

        // ===== DISKON (1–4) =====
        $discounts = [
            (float) ($this->order->diskon_1 ?? 0),
            (float) ($this->order->diskon_2 ?? 0),
            (float) ($this->order->diskon_3 ?? 0),
            (float) ($this->order->diskon_4 ?? 0),
        ];

        $diskonGabungan = collect($discounts)
            ->filter(fn ($v) => $v > 0)
            ->map(function ($v) {
                $v = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
                return $v . '%';
            })
            ->implode(' + ') ?: '0%';

        $penjelasanDiskon = collect([
            trim($this->order->penjelasan_diskon_1 ?? ''),
            trim($this->order->penjelasan_diskon_2 ?? ''),
            trim($this->order->penjelasan_diskon_3 ?? ''),
            trim($this->order->penjelasan_diskon_4 ?? ''),
        ])->filter()->implode(' + ');

        $no = 1;

        // ===== GROUPING PRODUK (Brand+Category+Product+Warna+Barcode) =====
        $groupedItems = collect($this->order->productsWithDetails() ?? [])
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

                $rawTotal = collect($group)->sum(function ($i) {
                    $q = (int) ($i['quantity'] ?? 0);
                    $p = (int) ($i['price'] ?? 0);
                    return $q * $p;
                });

                $first['quantity']  = $qtyTotal;
                $first['raw_total'] = $rawTotal;

                return $first;
            })
            ->values();

        // ===== HITUNG TOTAL KESELURUHAN DULU =====
        $subTotal           = 0;
        $totalAfterDiscount = 0;

        foreach ($groupedItems as $gi) {
            $qty   = (int) ($gi['quantity'] ?? 0);
            $harga = (int) ($gi['price'] ?? 0);

            $totalAwal = (int) ($gi['raw_total'] ?? ($qty * $harga));
            $subTotal += $totalAwal;

            $amount = (float) $totalAwal;
            foreach ($discounts as $d) {
                $d = max(0, min(100, (float) $d));
                if ($d > 0) {
                    $amount -= $amount * ($d / 100);
                }
            }
            $totalAfterDiscount += (int) round($amount);
        }

        $discountAmount   = $subTotal - $totalAfterDiscount;
        $dataRowsCount    = $groupedItems->count();
        $this->dataEndRow = 2 + $dataRowsCount; // row 1 title, row 2 header

        // ===== DATA BARIS DETAIL PRODUK (SUDAH DI-GROUP) =====
        foreach ($groupedItems as $item) {
            $brand    = $item['brand_name']    ?? '-';
            $category = $item['category_name'] ?? '-';
            $product  = $item['product_name']  ?? '-';
            $color    = $item['color']         ?? '-';

            $qty   = (int) ($item['quantity'] ?? 0);
            $harga = (int) ($item['price'] ?? 0);

            $rows[] = [
                $no++,
                $this->dashIfEmpty($this->order->no_order),
                $this->dashIfEmpty(optional($this->order->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty(optional($this->order->updated_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($this->order->customer->name ?? null),

                $this->dashIfEmpty($item['barcode'] ?? null),
                $this->dashIfEmpty($brand),
                $this->dashIfEmpty($category),
                $this->dashIfEmpty($product),
                $this->dashIfEmpty($color),

                $this->dashIfEmpty($qty),
                'Rp ' . number_format($harga, 0, ',', '.'),

                $this->dashIfEmpty($diskonGabungan),
                'Rp ' . number_format($totalAfterDiscount, 0, ',', '.'),

                $this->dashIfEmpty($this->order->payment_method),
                $this->dashIfEmpty(optional($this->order->payment_due_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($this->order->employee->name ?? null),
                $this->dashIfEmpty($this->order->department->name ?? null),
                $this->dashIfEmpty(optional($this->order->customer?->customerProgram)->name ?? 'Tidak Ikut Program'),
                $this->dashIfEmpty($this->order->customerCategory->name ?? null),
                $this->mapStatusPembayaran($this->order->status_pembayaran ?? null),
                $this->mapStatusPengajuan($this->order->status_pengajuan ?? null),
                $this->mapStatusProduct($this->order->status_product ?? null),
                $this->mapStatusOrder($this->order->status_order ?? null),
                $this->dashIfEmpty(optional($this->order->on_hold_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($this->order->on_hold_comment ?? '-'),
                empty($this->imagePaths) ? '-' : '',
            ];
        }

        // ===== RINGKASAN TOTAL (TABEL TERPISAH) =====
        $colCount = count($headers);

        // dua baris kosong
        $rows[] = array_fill(0, $colCount, '');
        $rows[] = array_fill(0, $colCount, '');

        // label di kolom ke-2 dari belakang, nilai di kolom terakhir
        $pad = max(0, $colCount - 2);

        $rows[] = array_merge(
            array_fill(0, $pad, ''),
            ['Sub Total:', 'Rp ' . number_format($subTotal, 0, ',', '.')]
        );

        $rows[] = array_merge(
            array_fill(0, $pad, ''),
            ['Discount:', $discountAmount > 0 ? 'Rp ' . number_format($discountAmount, 0, ',', '.') : '-']
        );

        $rows[] = array_merge(
            array_fill(0, $pad, ''),
            ['Total Akhir:', 'Rp ' . number_format($totalAfterDiscount, 0, ',', '.')]
        );

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet        = $event->sheet->getDelegate();
                $lastCol      = $sheet->getHighestColumn();
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);

                // data mulai baris 3 sampai 3 + jumlah item - 1
                $startRow = 3;
                $dataRows = max(0, count($this->order->productsWithDetails()));
                $endRow   = $startRow + $dataRows - 1;

                if ($dataRows === 0) {
                    return;
                }

                $imgCol = Coordinate::stringFromColumnIndex($lastColIndex);
                $sheet->getColumnDimension($imgCol)->setWidth(40);

                // set row height
                for ($row = $startRow; $row <= $endRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(65);
                }

                if (!empty($this->imagePaths)) {
                    $offsetX = 5;
                    foreach (array_slice($this->imagePaths, 0, 3) as $path) {
                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setWorksheet($sheet);
                        $drawing->setCoordinates($imgCol . $startRow);
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setHeight(55);
                        $offsetX += 60;
                    }
                } else {
                    $sheet->setCellValue($imgCol . $startRow, '-');
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

        // ===== STYLE TABEL DETAIL (sampai dataEndRow saja) =====
        $dataEndRow = $this->dataEndRow > 0 ? $this->dataEndRow : $highestRow;

        for ($row = 3; $row <= $dataEndRow; $row++) {
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

        // ===== STYLE TABEL RINGKASAN (terpisah) =====
        if ($dataEndRow < $highestRow) {
            $labelColIdx  = $lastColIdx - 1;
            $labelCol     = Coordinate::stringFromColumnIndex($labelColIdx);
            $valueCol     = Coordinate::stringFromColumnIndex($lastColIdx);

            // ringkasan selalu 3 baris terakhir (setelah 2 baris kosong)
            $summaryStart = $dataEndRow + 3; // lompat 2 baris kosong
            if ($summaryStart <= $highestRow) {
                // bersihkan border di semua kolom ringkasan biar ga nyatu
                for ($row = $summaryStart; $row <= $highestRow; $row++) {
                    for ($i = 1; $i <= $lastColIdx; $i++) {
                        $col = Coordinate::stringFromColumnIndex($i);
                        $sheet->getStyle("{$col}{$row}")->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
                        ]);
                    }
                }

                // kasih border hanya di 2 kolom (label & value)
                $sheet->getStyle("{$labelCol}{$summaryStart}:{$valueCol}{$highestRow}")
                    ->applyFromArray([
                        'font'      => ['bold' => false],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true,
                        ],
                        'borders'   => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                        ],
                    ]);

                // khusus kolom nilai → rata kanan
                $sheet->getStyle("{$valueCol}{$summaryStart}:{$valueCol}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        // Autosize semua kolom
        for ($i = 1; $i <= $lastColIdx; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
