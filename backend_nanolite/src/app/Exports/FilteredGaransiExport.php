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

class FilteredGaransiExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** @var array<int, array<int,string>> rowIndex => [paths...] */
    protected array $imageMap = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
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
            $txt = implode(', ', array_filter($parts, fn ($v) => $v && $v !== '-'));
            return $txt !== '' ? $txt : '-';
        }
        return $address ?: '-';
    }

    protected function parseImagePaths($images): array
    {
        if (is_string($images) && str_starts_with($images, '[')) {
            $images = json_decode($images, true);
        }
        $arr = [];
        if (is_array($images)) $arr = $images;
        elseif (is_string($images) && $images !== '') $arr = [$images];

        $paths = [];
        foreach ($arr as $p) {
            $p = preg_replace('#^/?storage/#', '', $p);
            $abs = storage_path('app/public/' . ltrim($p, '/'));
            if (is_file($abs)) $paths[] = $abs;
            if (count($paths) >= 3) break;
        }
        return $paths;
    }

    protected function applyManualFilters($garansis)
    {
        if (!empty($this->filters['brand_id'])) {
            $garansis = $garansis->filter(function ($g) {
                foreach ($g->productsWithDetails() as $i) {
                    if (($i['brand_id'] ?? null) == $this->filters['brand_id']) return true;
                }
                return false;
            });
        }
        if (!empty($this->filters['category_id'])) {
            $garansis = $garansis->filter(function ($g) {
                foreach ($g->productsWithDetails() as $i) {
                    if (($i['category_id'] ?? null) == $this->filters['category_id']) return true;
                }
                return false;
            });
        }
        if (!empty($this->filters['product_id'])) {
            $garansis = $garansis->filter(function ($g) {
                foreach ($g->productsWithDetails() as $i) {
                    if (($i['product_id'] ?? null) == $this->filters['product_id']) return true;
                }
                return false;
            });
        }
        return $garansis;
    }

    public function array(): array
    {
        $q = Garansi::with(['customer.customerCategory','employee','department'])
            ->orderBy('created_at', 'asc');

        if (!empty($this->filters['department_id']))          $q->where('department_id', $this->filters['department_id']);
        if (!empty($this->filters['customer_id']))            $q->where('customer_id', $this->filters['customer_id']);
        if (!empty($this->filters['employee_id']))            $q->where('employee_id', $this->filters['employee_id']);
        if (!empty($this->filters['customer_categories_id'])) $q->where('customer_categories_id', $this->filters['customer_categories_id']);
        if (!empty($this->filters['status_pengajuan']))       $q->where('status_pengajuan', $this->filters['status_pengajuan']);
        if (!empty($this->filters['status_product']))         $q->where('status_product', $this->filters['status_product']);
        if (!empty($this->filters['status_garansi']))         $q->where('status_garansi', $this->filters['status_garansi']);

        $garansis = $this->applyManualFilters($q->get());

        $headers = [
            'No.',
            'No Garansi',
            'Tanggal Pembelian',
            'Tanggal Klaim',
            'Department',
            'Karyawan',
            'Customer',
            'Kategori Customer',
            'Phone',
            'Alamat',
            'Item Description',
            'Pcs',
            'Alasan Klaim',
            'Catatan',
            'Status Pengajuan',
            'Status Produk',
            'Status Garansi',
            'Komentar Ditolak',     // ⬅ baru
            'Komentar Cancelled',   // ⬅ baru
            'Batas Hold',
            'Alasan Hold',
            'Tanggal Dibuat',
            'Tanggal Diupdate',
            'Bukti Pengiriman',
        ];

        $rows = [
            array_pad([], max(1, count($headers) - 12), ''),
            $headers,
        ];
        $rows[0][(int) floor(count($headers) / 2)] = 'GARANSI';

        $no = 1;
        $startRow   = 3;
        $currentRow = $startRow;

        foreach ($garansis as $g) {
            $items = $g->productsWithDetails();
            $desc  = collect($items)->map(function ($i) {
                $name = "{$i['brand_name']} – {$i['category_name']} – {$i['product_name']}";
                $color = $i['color'] ?? '';
                $qty = (int) ($i['quantity'] ?? 0);
                return trim("{$name} {$color} ({$qty} pcs)");
            })->implode("\n");
            $totalPcs = collect($items)->sum(fn ($i) => (int) ($i['quantity'] ?? 0));

            // simpan image paths untuk baris ini
            $this->imageMap[$currentRow] = $this->parseImagePaths($g->delivery_images);

            $rows[] = [
                $no++,
                $g->no_garansi ?? '-',
                optional($g->purchase_date)->format('Y-m-d') ?? '-',
                optional($g->claim_date)->format('Y-m-d') ?? '-',
                $g->department->name ?? '-',
                $g->employee->name ?? '-',
                $g->customer->name ?? '-',
                $g->customer?->customerCategory?->name ?? '-',
                $g->phone ?? '-',
                $this->formatAddress($g->address),
                $desc ?: '-',
                $totalPcs ?: 0,
                $g->reason ?? '-',
                $g->note ?? '-',
                $g->status_pengajuan ?? $g->status ?? '-',
                $g->status_product ?? '-',
                $g->status_garansi ?? '-',
                $g->rejection_comment ?: '-',   // alasan ditolak
                $g->cancelled_comment ?: '-',   // alasan cancel
                optional($g->on_hold_until)->format('Y-m-d') ?? '-',
                $g->on_hold_comment ?: '-',
                optional($g->created_at)->format('Y-m-d H:i'),
                optional($g->updated_at)->format('Y-m-d H:i'),
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
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);

                $imgColIndex = $lastColIndex;
                $imgCol = Coordinate::stringFromColumnIndex($imgColIndex);

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
        $lastCol = $sheet->getHighestColumn();

        // judul
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'GARANSI');
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
        $highestRow   = $sheet->getHighestRow();
        $lastColIndex = Coordinate::columnIndexFromString($lastCol);
        for ($row = 3; $row <= $highestRow; $row++) {
            for ($i = 1; $i <= $lastColIndex; $i++) {
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

        // autosize kecuali kolom gambar
        for ($i = 1; $i <= $lastColIndex - 1; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
