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

        // judul di tengah baris pertama
        $rows[0][(int) floor(count($headers) / 2)] = 'SALES ORDER';

        // simpan path gambar delivery
        $this->imagePaths = $this->parseImagePaths($this->order->delivery_images);

        // ===== DATA =====
        $no = 1;
        $diskon1 = (float) $this->order->diskon_1;
        $diskon2 = (float) $this->order->diskon_2;
        $diskonGabungan = collect([$diskon1, $diskon2])
            ->filter(fn ($v) => $v > 0)
            ->map(fn ($v) => "{$v}%")
            ->implode(' + ') ?: '0%';

        $penjelasanDiskon = collect([
            trim($this->order->penjelasan_diskon_1 ?? '-'),
            trim($this->order->penjelasan_diskon_2 ?? '-'),
        ])
            ->filter()
            ->implode(' + ');

        $subTotal = 0;
        $totalAfterDiscount = 0;

        foreach ($this->order->productsWithDetails() as $item) {
            $desc   = "{$item['brand_name']} – {$item['category_name']} – {$item['product_name']} {$item['color']}";
            $qty    = (int) $item['quantity'];
            $harga  = (int) $item['price'];
            $totalAwal = $qty * $harga;

            $afterFirst  = $totalAwal * (1 - ($diskon1 / 100));
            $afterSecond = $afterFirst * (1 - ($diskon2 / 100));
            $amount      = (int) round($afterSecond);

            $subTotal           += $totalAwal;
            $totalAfterDiscount += $amount;

            $rows[] = [
                $no++,
                $this->dashIfEmpty($this->order->no_order),
                $this->dashIfEmpty(optional($this->order->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty(optional($this->order->updated_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($this->order->department->name ?? null),
                $this->dashIfEmpty($this->order->employee->name ?? null),
                $this->dashIfEmpty($this->order->customer->name ?? null),
                $this->dashIfEmpty($this->order->customerCategory->name ?? null),
                $this->dashIfEmpty(optional($this->order->customer?->customerProgram)->name ?? 'Tidak Ikut Program'),
                $this->dashIfEmpty($this->order->phone ?? null),
                $this->dashIfEmpty(
                    is_array($this->order->address)
                        ? ($this->order->address['detail_alamat'] ?? null)
                        : ($this->order->address ?? null)
                ),
                $this->dashIfEmpty($desc),
                $this->dashIfEmpty($qty),
                $this->dashIfEmpty($harga),
                $this->dashIfEmpty($totalAwal),
                $this->dashIfEmpty($this->order->jumlah_program),
                $this->dashIfEmpty($this->order->reward_point),
                $this->dashIfEmpty($diskonGabungan),
                $this->dashIfEmpty($penjelasanDiskon),
                $this->dashIfEmpty($this->order->payment_method),
                $this->mapStatusPembayaran($this->order->status_pembayaran ?? null),
                $this->mapStatusPengajuan($this->order->status_pengajuan ?? null),
                $this->mapStatusProduct($this->order->status_product ?? null),
                $this->mapStatusOrder($this->order->status_order ?? null),
                $this->dashIfEmpty($this->order->rejection_comment ?? '-'),
                $this->dashIfEmpty($this->order->cancelled_comment ?? '-'),
                $this->dashIfEmpty(optional($this->order->on_hold_until)?->format('Y-m-d') ?? '-'),
                $this->dashIfEmpty($this->order->on_hold_comment ?? '-'),
                empty($this->imagePaths) ? '-' : '', // kolom gambar (diisi di AfterSheet)
            ];
        }

        $discountAmount = $subTotal - $totalAfterDiscount;

        // ===== RINGKASAN TOTAL =====
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
                $sheet    = $event->sheet->getDelegate();
                $lastCol  = $sheet->getHighestColumn();
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
        $lastCol     = $sheet->getHighestColumn();
        $lastColIdx  = Coordinate::columnIndexFromString($lastCol);
        $highestRow  = $sheet->getHighestRow();

        // Judul: merge A1:LastCol1
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
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

        // Data rows (3..highestRow)
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

        // Autosize semua kolom
        for ($i = 1; $i <= $lastColIdx; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
