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

class FilteredReturnExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** @var array<int, array<int, string>> row => [imgPath,...] */
    protected array $productImageMap = [];
    /** @var array<int, array<int, string>> row => [imgPath,...] */
    protected array $deliveryImageMap = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
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
        return $address ?: '-';
    }

    public function array(): array
    {
        $q = ProductReturn::with(['customer.customerCategory','employee','department','category'])
            ->orderBy('created_at','asc');

        if (!empty($this->filters['department_id']))          $q->where('department_id', $this->filters['department_id']);
        if (!empty($this->filters['employee_id']))            $q->where('employee_id', $this->filters['employee_id']);
        if (!empty($this->filters['customer_id']))            $q->where('customer_id', $this->filters['customer_id']);
        if (!empty($this->filters['customer_categories_id'])) $q->where('customer_categories_id', $this->filters['customer_categories_id']);
        if (!empty($this->filters['status']))                 $q->where('status', $this->filters['status']);

        $returns = $this->applyManualFilters($q->get());

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

        // baris judul
        $rows = [
            array_fill(0, count($headers), ''),
            $headers,
        ];
        $rows[0][(int) floor(count($headers) / 2)] = 'PRODUCT RETURN';

        $no         = 1;
        $currentRow = 3;

        foreach ($returns as $r) {
            $items = $r->productsWithDetails();
            $desc = collect($items)->map(function ($i) {
                $name  = "{$i['brand_name']} – {$i['category_name']} – {$i['product_name']}";
                $color = $i['color'] ?? '';
                $qty   = (int)($i['quantity'] ?? 0);
                return trim("$name $color ({$qty} pcs)");
            })->implode("\n");
            $totalPcs = collect($items)->sum(fn ($i) => (int)($i['quantity'] ?? 0));

            // simpan path gambar per baris
            $this->productImageMap[$currentRow]  = $this->parseImagePaths($r->image);
            $this->deliveryImageMap[$currentRow] = $this->parseImagePaths($r->delivery_images);

            $rows[] = [
                $no++,
                $r->no_return ?? '-',
                $r->department->name ?? '-',
                $r->employee->name ?? '-',
                $r->customer->name ?? '-',
                $r->category->name ?? '-',
                $r->phone ?? '-',
                $this->formatAddress($r->address),
                $desc ?: '-',
                $totalPcs ?: 0,
                $r->reason ?? '-',
                $r->note ?? '-',
                'Rp ' . number_format((int) $r->amount, 0, ',', '.'),
                match ($r->status_pengajuan) {
                    'pending'  => 'Pending',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default    => ucfirst((string) $r->status_pengajuan),
                },
                match ($r->status_product) {
                    'pending'     => 'Pending',
                    'ready_stock' => 'Ready Stock',
                    'sold_out'    => 'Sold Out',
                    'rejected'    => 'Ditolak',
                    default       => ucfirst((string) $r->status_product),
                },
                match ($r->status_return) {
                    'pending'    => 'Pending',
                    'confirmed'  => 'Confirmed',
                    'processing' => 'Processing',
                    'on_hold'    => 'On Hold',
                    'delivered'  => 'Delivered',
                    'completed'  => 'Completed',
                    'cancelled'  => 'Cancelled',
                    'rejected'   => 'Ditolak',
                    default      => ucfirst((string) $r->status_return),
                },
                $r->rejection_comment ?: '-',   // ⬅ alasan ditolak
                $r->cancelled_comment ?: '-',   // ⬅ alasan cancel
                optional($r->on_hold_until)->format('Y-m-d') ?? '-',
                $r->on_hold_comment ?: '-',
                empty($this->productImageMap[$currentRow])  ? '-' : '',
                empty($this->deliveryImageMap[$currentRow]) ? '-' : '',
                optional($r->created_at)->format('Y-m-d H:i'),
                optional($r->updated_at)->format('Y-m-d H:i'),
            ];

            $currentRow++;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);

                // POSISI: ... Foto Barang, Bukti Pengiriman, Tgl Dibuat, Tgl Diupdate
                $fotoColIndex     = $lastColIndex - 3;
                $deliveryColIndex = $lastColIndex - 2;

                $fotoCol     = Coordinate::stringFromColumnIndex($fotoColIndex);
                $deliveryCol = Coordinate::stringFromColumnIndex($deliveryColIndex);

                // lebar kolom gambar
                $sheet->getColumnDimension($fotoCol)->setWidth(40);
                $sheet->getColumnDimension($deliveryCol)->setWidth(40);

                // tanam foto barang
                foreach ($this->productImageMap as $row => $paths) {
                    $sheet->getRowDimension($row)->setRowHeight(65);
                    if (empty($paths)) {
                        $sheet->setCellValue($fotoCol . $row, '-');
                        continue;
                    }
                    $ox = 5;
                    foreach (array_slice($paths, 0, 3) as $path) {
                        $d = new Drawing();
                        $d->setPath($path);
                        $d->setWorksheet($sheet);
                        $d->setCoordinates($fotoCol . $row);
                        $d->setOffsetX($ox);
                        $d->setOffsetY(3);
                        $d->setHeight(55);
                        $ox += 60;
                    }
                }

                // tanam bukti pengiriman
                foreach ($this->deliveryImageMap as $row => $paths) {
                    $sheet->getRowDimension($row)->setRowHeight(65);
                    if (empty($paths)) {
                        $sheet->setCellValue($deliveryCol . $row, '-');
                        continue;
                    }
                    $ox = 5;
                    foreach (array_slice($paths, 0, 3) as $path) {
                        $d = new Drawing();
                        $d->setPath($path);
                        $d->setWorksheet($sheet);
                        $d->setCoordinates($deliveryCol . $row);
                        $d->setOffsetX($ox);
                        $d->setOffsetY(3);
                        $d->setHeight(55);
                        $ox += 60;
                    }
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
