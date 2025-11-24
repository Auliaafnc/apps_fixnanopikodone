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

    protected function dash($v): string
    {
        return (is_null($v) || trim((string) $v) === '') ? '-' : (string) $v;
    }

    protected function formatAddress($address): string
    {
        if (is_array($address)) {
            $parts = [
                $address['detail_alamat'] ?? null,
                $address['kelurahan'] ?? null,
                $address['kecamatan'] ?? null,
                $address['kota_kab'] ?? null,
                $address['provinsi'] ?? null,
                $address['kode_pos'] ?? null,
            ];
            $txt = implode(', ', array_filter($parts, fn ($x) => $x && $x !== '-'));
            return $txt !== '' ? $txt : '-';
        }
        return $this->dash($address);
    }

    protected function parseImagePaths($images): array
    {
        if (is_string($images) && str_starts_with($images, '[')) {
            $images = json_decode($images, true);
        }
        $arr = is_array($images) ? $images : ((is_string($images) && $images !== '') ? [$images] : []);
        $paths = [];
        foreach ($arr as $p) {
            $p = preg_replace('#^/?storage/#', '', $p);
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
        // urutan kolom: ... Status Return, Alasan Ditolak, Alasan Cancelled, Batas Hold, Alasan Hold, Foto Barang, Bukti Pengiriman, Tanggal Dibuat, Tanggal Diupdate
        $headers = [
            'No.',
            'No Return',
            'Department',
            'Karyawan',
            'Customer',
            'Kategori Customer',
            'Phone',
            'Alamat',
            'Item Description',
            'Pcs',
            'Alasan Return',
            'Catatan',
            'Nominal',
            'Status Pengajuan',
            'Status Produk',
            'Status Return',
            'Alasan Ditolak',      // ⬅ baru
            'Alasan Cancelled',    // ⬅ baru
            'Batas Hold',
            'Alasan Hold',
            'Foto Barang',
            'Bukti Pengiriman',
            'Tanggal Dibuat',
            'Tanggal Diupdate',
        ];

        $rows = [
            array_fill(0, count($headers), ''),
            $headers,
        ];
        $rows[0][(int) floor(count($headers) / 2)] = 'PRODUCT RETURN';

        // kumpulkan path gambar
        $this->productImagePaths  = $this->parseImagePaths($this->return->image);
        $this->deliveryImagePaths = $this->parseImagePaths($this->return->delivery_images);

        $no = 1;
        foreach ($this->return->productsWithDetails() as $item) {
            $desc = "{$item['brand_name']} – {$item['category_name']} – {$item['product_name']} " . ($item['color'] ?? '');
            $qty  = (int) ($item['quantity'] ?? 0);

            $rows[] = [
                $no++,
                $this->dash($this->return->no_return),
                $this->dash($this->return->department->name ?? null),
                $this->dash($this->return->employee->name ?? null),
                $this->dash($this->return->customer->name ?? null),
                $this->dash($this->return->category->name ?? null),
                $this->dash($this->return->phone ?? null),
                $this->formatAddress($this->return->address),
                $this->dash($desc),
                $this->dash($qty),
                $this->dash($this->return->reason ?? '-'),
                $this->dash($this->return->note ?? '-'),
                'Rp ' . number_format((int) $this->return->amount, 0, ',', '.'),
                $this->dash(match ($this->return->status_pengajuan) {
                    'pending'  => 'Pending',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default    => ucfirst((string) $this->return->status_pengajuan),
                }),
                $this->dash(match ($this->return->status_product) {
                    'pending'     => 'Pending',
                    'ready_stock' => 'Ready Stock',
                    'sold_out'    => 'Sold Out',
                    'rejected'    => 'Ditolak',
                    default       => ucfirst((string) $this->return->status_product),
                }),
                $this->dash(match ($this->return->status_return) {
                    'pending'    => 'Pending',
                    'confirmed'  => 'Confirmed',
                    'processing' => 'Processing',
                    'on_hold'    => 'On Hold',
                    'delivered'  => 'Delivered',
                    'completed'  => 'Completed',
                    'cancelled'  => 'Cancelled',
                    'rejected'   => 'Ditolak',
                    default      => ucfirst((string) $this->return->status_return),
                }),
                $this->dash($this->return->rejection_comment ?? '-'),  // ⬅ alasan ditolak
                $this->dash($this->return->cancelled_comment ?? '-'),  // ⬅ alasan cancel
                $this->dash(optional($this->return->on_hold_until)?->format('Y-m-d')),
                $this->dash($this->return->on_hold_comment ?: '-'),
                empty($this->productImagePaths)  ? '-' : '',
                empty($this->deliveryImagePaths) ? '-' : '',
                $this->dash(optional($this->return->created_at)->format('Y-m-d H:i')),
                $this->dash(optional($this->return->updated_at)->format('Y-m-d H:i')),
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);

                // POSISI: Foto Barang = -3, Bukti Pengiriman = -2, terakhir 2 kolom tanggal
                $productColIndex  = $lastColIndex - 3;
                $deliveryColIndex = $lastColIndex - 2;

                $productCol  = Coordinate::stringFromColumnIndex($productColIndex);
                $deliveryCol = Coordinate::stringFromColumnIndex($deliveryColIndex);

                // range baris data
                $startRow = 3;
                $dataRows = max(0, count($this->return->productsWithDetails()));
                if ($dataRows === 0) return;
                $endRow = $startRow + $dataRows - 1;

                // set lebar dan tinggi
                $sheet->getColumnDimension($productCol)->setWidth(40);
                $sheet->getColumnDimension($deliveryCol)->setWidth(40);
                for ($r = $startRow; $r <= $endRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(65);
                }

                // tanam foto barang
                if (!empty($this->productImagePaths)) {
                    $ox = 5;
                    foreach (array_slice($this->productImagePaths, 0, 3) as $path) {
                        $d = new Drawing();
                        $d->setPath($path);
                        $d->setWorksheet($sheet);
                        $d->setCoordinates($productCol . $startRow);
                        $d->setOffsetX($ox);
                        $d->setOffsetY(3);
                        $d->setHeight(55);
                        $ox += 60;
                    }
                } else {
                    $sheet->setCellValue($productCol . $startRow, '-');
                }

                // tanam bukti pengiriman
                if (!empty($this->deliveryImagePaths)) {
                    $ox = 5;
                    foreach (array_slice($this->deliveryImagePaths, 0, 3) as $path) {
                        $d = new Drawing();
                        $d->setPath($path);
                        $d->setWorksheet($sheet);
                        $d->setCoordinates($deliveryCol . $startRow);
                        $d->setOffsetX($ox);
                        $d->setOffsetY(3);
                        $d->setHeight(55);
                        $ox += 60;
                    }
                } else {
                    $sheet->setCellValue($deliveryCol . $startRow, '-');
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();

        // Title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'PRODUCT RETURN');
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
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
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ]);

        // Data + autosize (skip 2 kolom gambar)
        $highestRow   = $sheet->getHighestRow();
        $lastColIndex = Coordinate::columnIndexFromString($lastCol);

        for ($row = 3; $row <= $highestRow; $row++) {
            for ($i = 1; $i <= $lastColIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_TOP,
                        'wrapText'   => true,
                    ],
                ]);
            }
        }

        $img1 = $lastColIndex - 3; // Foto Barang
        $img2 = $lastColIndex - 2; // Bukti Pengiriman
        for ($i = 1; $i <= $lastColIndex; $i++) {
            if ($i === $img1 || $i === $img2) {
                continue;
            }
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return [];
    }
}
