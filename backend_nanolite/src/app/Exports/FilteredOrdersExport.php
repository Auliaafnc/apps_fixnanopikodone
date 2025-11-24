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
        ]);

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

        if (isset($this->filters['has_diskon'])) {
            $query->where('diskons_enabled', $this->filters['has_diskon'] === 'ya');
        }

        if (isset($this->filters['has_program_point'])) {
            $query->where('program_enabled', $this->filters['has_program_point'] === 'ya');
        }

        if (isset($this->filters['has_reward_point'])) {
            $query->where('reward_enabled', $this->filters['has_reward_point'] === 'ya');
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

        // ===== HEADER =====
        $headers = [
            'No.',
            'No Order',
            'Tanggal Dibuat',
            'Tanggal Diupdate',
            'Department',
            'Karyawan',
            'Customer',
            'Kategori Customer',
            'Customer Program',
            'Phone',
            'Alamat',
            'Item Description',
            'Pcs',
            'Unit Price',
            'Total Awal',
            'Program Point',
            'Reward Point',
            'Disc%',
            'Penjelasan Diskon',
            'Total Akhir',
            'Metode Pembayaran',
            'Status Pembayaran',
            'Status Pengajuan',
            'Status Produk',
            'Status Order',
            'Alasan Ditolak',
            'Alasan Cancelled',
            'Batas Hold',          // ⬅ baru
            'Alasan Hold',         // ⬅ baru
            'Bukti Pengiriman',    // ⬅ baru (gambar)
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
            $diskon1 = (float) $order->diskon_1;
            $diskon2 = (float) $order->diskon_2;
            $diskonGabungan = collect([$diskon1, $diskon2])
                ->filter(fn ($v) => $v > 0)
                ->map(fn ($v) => "{$v}%")
                ->implode(' + ') ?: '0%';

            $penjelasanDiskon = collect([
                trim($order->penjelasan_diskon_1 ?? '-'),
                trim($order->penjelasan_diskon_2 ?? '-'),
            ])->filter()->implode(' + ');

            $deskripsiProduk = [];
            $hargaProduk     = [];
            $totalPcs        = 0;
            $totalAwalSemuaProduk = 0;

            foreach ($order->productsWithDetails() as $item) {
                $desc  = "{$item['brand_name']} – {$item['category_name']} – {$item['product_name']} {$item['color']}";
                $qty   = (int) $item['quantity'];
                $harga = (int) $item['price'];
                $totalAwal = $qty * $harga;

                $totalPcs += $qty;
                $totalAwalSemuaProduk += $totalAwal;

                $deskripsiProduk[] = "$desc ({$qty} pcs)";
                $hargaProduk[]     = "Rp " . number_format($harga, 0, ',', '.') .
                    " x {$qty} = Rp " . number_format($totalAwal, 0, ',', '.');
            }

            // simpan image paths untuk baris ini
            $this->imageMap[$currentRow] = $this->parseImagePaths($order->delivery_images);

            $rows[] = [
                $no++,
                $this->dashIfEmpty($order->no_order),
                $this->dashIfEmpty(optional($order->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty(optional($order->updated_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($order->department->name ?? '-'),
                $this->dashIfEmpty($order->employee->name ?? '-'),
                $this->dashIfEmpty($order->customer->name ?? '-'),
                $this->dashIfEmpty($order->customer->customerCategory->name ?? '-'),
                $this->dashIfEmpty($order->customerProgram->name ?? 'Tidak Ikut Program'),
                $this->dashIfEmpty($order->phone ?? '-'),
                $this->dashIfEmpty($order->address ?? '-'),
                implode("\n", $deskripsiProduk),
                $totalPcs,
                implode("\n", $hargaProduk),
                'Rp ' . number_format($totalAwalSemuaProduk, 0, ',', '.'),
                $this->dashIfEmpty($order->jumlah_program ?? '-'),
                $this->dashIfEmpty($order->reward_point ?? '-'),
                $diskonGabungan,
                $penjelasanDiskon,
                'Rp ' . number_format($order->totalAfterDiscount, 0, ',', '.'),
                $this->dashIfEmpty($order->payment_method ?? '-'),
                $this->mapStatusPembayaran($order->status_pembayaran ?? null),
                $this->mapStatusPengajuan($order->status_pengajuan ?? null),
                $this->mapStatusProduct($order->status_product ?? null),
                $this->mapStatusOrder($order->status_order ?? null),
                $this->dashIfEmpty($order->rejection_comment ?? '-'),
                $this->dashIfEmpty($order->cancelled_comment ?? '-'),
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
