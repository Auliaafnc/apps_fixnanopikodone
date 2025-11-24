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
    protected array $imagePaths = [];

    public function __construct(Garansi $garansi)
    {
        $this->garansi = $garansi;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
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
        return $this->dashIfEmpty($address);
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

    public function array(): array
    {
        $headers = [
            'No.',
            'No Garansi',
            'Tanggal Dibuat',
            'Tanggal Diupdate',
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
            'Tanggal Pembelian',
            'Tanggal Klaim',
            'Bukti Pengiriman',     // kolom gambar (tetap terakhir)
        ];

        $rows = [
            array_pad([], max(1, count($headers) - 12), ''),
            $headers,
        ];
        $rows[0][(int) floor(count($headers) / 2)] = 'GARANSI';

        // ambil max 3 gambar bukti pengiriman
        $this->imagePaths = $this->parseImagePaths($this->garansi->delivery_images);

        $no = 1;

        foreach ($this->garansi->productsWithDetails() as $item) {
            $desc  = "{$item['brand_name']} – {$item['category_name']} – {$item['product_name']} " . ($item['color'] ?? '');
            $qty   = (int) ($item['quantity'] ?? 0);

            $rows[] = [
                $no++,
                $this->dashIfEmpty($this->garansi->no_garansi),
                $this->dashIfEmpty(optional($this->garansi->created_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty(optional($this->garansi->updated_at)->format('Y-m-d H:i')),
                $this->dashIfEmpty($this->garansi->department->name ?? null),
                $this->dashIfEmpty($this->garansi->employee->name ?? null),
                $this->dashIfEmpty($this->garansi->customer->name ?? null),
                $this->dashIfEmpty($this->garansi->customerCategory->name ?? null),
                $this->dashIfEmpty($this->garansi->phone ?? null),
                $this->formatAddress($this->garansi->address),
                $this->dashIfEmpty($desc),
                $this->dashIfEmpty($qty),
                $this->dashIfEmpty($this->garansi->reason ?? '-'),
                $this->dashIfEmpty($this->garansi->note ?? '-'),
                $this->dashIfEmpty($this->garansi->status_pengajuan ?? $this->garansi->status ?? '-'),
                $this->dashIfEmpty($this->garansi->status_product ?? '-'),
                $this->dashIfEmpty($this->garansi->status_garansi ?? '-'),
                $this->dashIfEmpty($this->garansi->rejection_comment ?? '-'), // alasan ditolak
                $this->dashIfEmpty($this->garansi->cancelled_comment ?? '-'), // alasan cancel
                $this->dashIfEmpty(optional($this->garansi->on_hold_until)?->format('Y-m-d')),
                $this->dashIfEmpty($this->garansi->on_hold_comment ?: '-'),
                $this->dashIfEmpty(optional($this->garansi->purchase_date)?->format('Y-m-d')),
                $this->dashIfEmpty(optional($this->garansi->claim_date)?->format('Y-m-d')),
                empty($this->imagePaths) ? '-' : '', // kolom gambar (ditanam di AfterSheet)
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

                // data mulai baris 3 sampai 3 + jumlah item - 1
                $startRow = 3;
                $dataRows = max(0, count($this->garansi->productsWithDetails()));
                $endRow   = $startRow + $dataRows - 1;

                if ($dataRows === 0) return;

                $imgCol = Coordinate::stringFromColumnIndex($lastColIndex);
                $sheet->getColumnDimension($imgCol)->setWidth(40);
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

        // autosize kecuali kolom gambar (terakhir)
        for ($i = 1; $i <= $lastColIndex - 1; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
