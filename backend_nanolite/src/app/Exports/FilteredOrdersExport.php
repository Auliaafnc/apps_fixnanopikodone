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

class FilteredOrdersExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** @var array<int, array<int,string>> rowIndex => [paths...] */
    protected array $imageMap = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
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
     * Ambil max 3 path gambar dari delivery_images tiap order
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
        $query = Order::with([
            'customer.customerCategory',
            'employee',
            'customerProgram',
            'department',
        ]);

        // ===== FILTER DARI FORM EXPORT =====
        if (!empty($this->filters['customer_id'])) {
            $query->where('customer_id', $this->filters['customer_id']);
        }
        if (!empty($this->filters['department_id'])) {
            $query->where('department_id', $this->filters['department_id']);
        }
        if (!empty($this->filters['employee_id'])) {
            $query->where('employee_id', $this->filters['employee_id']);
        }
        if (!empty($this->filters['customer_categories_id'])) {
            $query->where('customer_categories_id', $this->filters['customer_categories_id']);
        }
        if (!empty($this->filters['customer_program_id'])) {
            $query->where('customer_program_id', $this->filters['customer_program_id']);
        }
        if (!empty($this->filters['payment_method'])) {
            $query->where('payment_method', $this->filters['payment_method']);
        }
        if (!empty($this->filters['status_pembayaran'])) {
            $query->where('status_pembayaran', $this->filters['status_pembayaran']);
        }

        // ➕ filter status_pengajuan
        if (!empty($this->filters['status_pengajuan'])) {
            $query->where('status_pengajuan', $this->filters['status_pengajuan']);
        }

        // ➕ filter status_order
        if (!empty($this->filters['status_order'])) {
            $query->where('status_order', $this->filters['status_order']);
        }

        // ➕ filter status_product
        if (!empty($this->filters['status_product'])) {
            $query->where('status_product', $this->filters['status_product']);
        }

        // filter diskon / reward / program
        if (isset($this->filters['has_diskon'])) {
            $query->where('diskons_enabled', $this->filters['has_diskon'] === 'ya');
        }
        if (isset($this->filters['has_program_point'])) {
            $query->where('program_enabled', $this->filters['has_program_point'] === 'ya');
        }
        if (isset($this->filters['has_reward_point'])) {
            $query->where('reward_enabled', $this->filters['has_reward_point'] === 'ya');
        }

        // filter tanggal dibuat (created_at)
        if (!empty($this->filters['created_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['created_from']);
        }
        if (!empty($this->filters['created_until'])) {
            $query->whereDate('created_at', '<=', $this->filters['created_until']);
        }

        $orders = $query->get();

        // Filter manual: brand
        if (!empty($this->filters['brand_id'])) {
            $orders = $orders->filter(function ($order) {
                foreach ($order->productsWithDetails() as $item) {
                    if (($item['brand_id'] ?? null) == $this->filters['brand_id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Filter manual: kategori
        if (!empty($this->filters['category_id'])) {
            $orders = $orders->filter(function ($order) {
                foreach ($order->productsWithDetails() as $item) {
                    if (($item['category_id'] ?? null) == $this->filters['category_id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Filter manual: produk
        if (!empty($this->filters['product_id'])) {
            $orders = $orders->filter(function ($order) {
                foreach ($order->productsWithDetails() as $item) {
                    if (($item['product_id'] ?? null) == $this->filters['product_id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        // ===== HEADER (Item Description dipisah jadi 4 kolom + Total Discount) =====
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
            'Total Discount',   // kolom baru
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
        $rows[0][(int) floor(count($headers) / 2)] = 'SALES ORDER';

        // ===== DATA =====
        $no         = 1;
        $startRow   = 3;
        $currentRow = $startRow;

        foreach ($orders as $order) {
            // diskon 1–4 (sama logika OrderExport)
            $discounts = [
                (float) ($order->diskon_1 ?? 0),
                (float) ($order->diskon_2 ?? 0),
                (float) ($order->diskon_3 ?? 0),
                (float) ($order->diskon_4 ?? 0),
            ];

            $diskonGabungan = collect($discounts)
                ->filter(fn ($v) => $v > 0)
                ->map(function ($v) {
                    $v = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
                    return $v . '%';
                })
                ->implode(' + ') ?: '0%';

            // group produk per order (Brand+Category+Product+Color+Barcode)
            $groupedItems = collect($order->productsWithDetails() ?? [])
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

                    $qtyTotal = collect($group)->sum(fn ($i) => (int) ($i['quantity'] ?? 0));
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

            // siapkan list per kolom (multiline)
            $brandList    = [];
            $categoryList = [];
            $productList  = [];
            $colorList    = [];
            $qtyList      = [];
            $hargaList    = [];
            $barcodeList  = [];

            $subTotal           = 0;
            $totalAfterDiscount = 0;

            foreach ($groupedItems as $item) {
                $brand    = $item['brand_name']    ?? '-';
                $category = $item['category_name'] ?? '-';
                $product  = $item['product_name']  ?? '-';
                $color    = $item['color']         ?? '-';
                $barcode  = $item['barcode']       ?? '-';

                $qty   = (int) ($item['quantity'] ?? 0);
                $harga = (int) ($item['price'] ?? 0);

                $brandList[]    = $brand;
                $categoryList[] = $category;
                $productList[]  = $product;
                $colorList[]    = $color;
                $barcodeList[]  = $barcode;
                $qtyList[]      = (string) $qty;
                $hargaList[]    = 'Rp ' . number_format($harga, 0, ',', '.');

                $totalAwal = (int) ($item['raw_total'] ?? ($qty * $harga));
                $subTotal += $totalAwal;

                $after = (float) $totalAwal;
                foreach ($discounts as $d) {
                    $d = max(0, min(100, (float) $d));
                    if ($d > 0) {
                        $after -= $after * ($d / 100);
                    }
                }
                $totalAfterDiscount += (int) round($after);
            }

            // total diskon per order
            $discountAmount = $subTotal - $totalAfterDiscount;

            // simpan image paths untuk baris ini
            $this->imageMap[$currentRow] = $this->parseImagePaths($order->delivery_images);

            $rows[] = [
                $no++,
                $this->dashIfEmpty($order->no_order),
                $this->dashIfEmpty(optional($order->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty(optional($order->updated_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($order->customer->name ?? '-'),

                implode("\n", array_map(fn ($b) => $this->dashIfEmpty($b), $barcodeList)),
                implode("\n", $brandList),
                implode("\n", $categoryList),
                implode("\n", $productList),
                implode("\n", $colorList),

                implode("\n", $qtyList),
                implode("\n", $hargaList),

                $this->dashIfEmpty($diskonGabungan),                               // Disc%
                'Rp ' . number_format($discountAmount, 0, ',', '.'),               // Total Discount
                'Rp ' . number_format($totalAfterDiscount, 0, ',', '.'),           // Total Akhir

                $this->dashIfEmpty($order->payment_method ?? '-'),
                $this->dashIfEmpty(optional($order->payment_due_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($order->employee->name ?? '-'),
                $this->dashIfEmpty($order->department->name ?? '-'),
                $this->dashIfEmpty($order->customerProgram->name ?? 'Tidak Ikut Program'),
                $this->dashIfEmpty($order->customer->customerCategory->name ?? '-'),
                $this->mapStatusPembayaran($order->status_pembayaran ?? null),
                $this->mapStatusPengajuan($order->status_pengajuan ?? null),
                $this->mapStatusProduct($order->status_product ?? null),
                $this->mapStatusOrder($order->status_order ?? null),
                $this->dashIfEmpty(optional($order->on_hold_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($order->on_hold_comment ?? '-'),
                empty($this->imageMap[$currentRow]) ? '-' : '',
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
                $imgCol      = Coordinate::stringFromColumnIndex($lastColIdx);

                // set lebar kolom gambar & tanam thumbnail
                $sheet->getColumnDimension($imgCol)->setWidth(40);

                foreach ($this->imageMap as $row => $paths) {
                    if (empty($paths)) {
                        $sheet->setCellValue($imgCol . $row, '-');
                        continue;
                    }

                    $sheet->getRowDimension($row)->setRowHeight(65);

                    $offsetX = 5;
                    foreach (array_slice($paths, 0, 3) as $path) {
                        $drawing = new Drawing();
                        $drawing->setPath($path);
                        $drawing->setWorksheet($sheet);
                        $drawing->setCoordinates($imgCol . $row);
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
        $sheet->setCellValue('A1', 'SALES ORDER');
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
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

        // autosize kecuali kolom gambar (biar width 40 tetap)
        for ($i = 1; $i <= $lastColIdx - 1; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
