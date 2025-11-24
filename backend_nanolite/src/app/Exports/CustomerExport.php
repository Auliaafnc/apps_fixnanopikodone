<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;
    protected $customersForDrawing;

    protected int $photoColumnWidthExcel = 16;
    protected int $pxPerExcelUnit       = 7;
    protected int $photoPaddingPx       = 10;
    protected int $maxPhotoHeightPx     = 80;

    public function __construct($filters = [])
    {
        $this->filters = is_array($filters) ? $filters : ($filters?->toArray() ?? []);
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '-' : (string) $value;
    }

    protected function firstImagePath($val): ?string
    {
        if (blank($val)) return null;

        if (is_string($val) && str_starts_with($val, '[')) {
            $decoded = json_decode($val, true);
            $val = is_array($decoded) ? ($decoded[0] ?? null) : $val;
        }

        if (is_array($val)) $val = $val[0] ?? null;

        if (blank($val)) return null;

        $val = preg_replace('#^/?storage/#', '', $val);

        if (preg_match('#^https?://#', $val)) {
            return null;
        }

        return ltrim($val, '/');
    }

    public function array(): array
    {
        $query = Customer::with([
            'employee',
            'department',
            'customerCategory',
            'customerProgram',
        ]);

        // Filter
        if (!empty($this->filters['department_id'])) $query->where('department_id', $this->filters['department_id']);
        if (!empty($this->filters['employee_id'])) $query->where('employee_id', $this->filters['employee_id']);
        if (!empty($this->filters['customer_categories_id'])) $query->where('customer_categories_id', $this->filters['customer_categories_id']);
        if (!empty($this->filters['customer_program_id'])) $query->where('customer_program_id', $this->filters['customer_program_id']);
        if (!empty($this->filters['status_pengajuan'])) $query->where('status_pengajuan', $this->filters['status_pengajuan']);
        if (!empty($this->filters['status'])) $query->where('status', $this->filters['status']);

        // Tambahkan agregasi poin
        $done = ['completed', 'delivered'];
        $query
            ->withSum(['orders as reward_point_sum' => function ($q) use ($done) {
                $q->whereIn('status_order', $done)
                  ->where('status_pengajuan', 'approved');
            }], 'reward_point')
            ->withSum(['orders as program_point_sum' => function ($q) use ($done) {
                $q->whereIn('status_order', $done)
                  ->where('status_pengajuan', 'approved');
            }], 'jumlah_program');

        $customers = $query->orderBy('employee_id')->orderBy('customer_categories_id')->orderBy('name')->get();
        $this->customersForDrawing = $customers;

        // Header
        $rows = [
            ['', '', '', '', '', '', '', '', '', 'DATA CUSTOMER', '', '', '', '', '', '', '', ''],
            [
                'No.',
                'ID',
                'Department',
                'Karyawan',
                'Nama',
                'Kategori Customer',
                'Telepon',
                'Email',
                'Alamat',
                'Link Google Maps',
                'Program',
                'Program Point',
                'Reward Point',
                'Gambar',
                'Status Pengajuan',
                'Status',
                'Dibuat Pada',
                'Diupdate Pada',
            ],
        ];

        // Data
        $no = 1;
        foreach ($customers as $cust) {
            $fullAddress = '-';
            if (is_array($cust->address)) {
                $fullAddress = collect($cust->address)->map(function ($addr) {
                    $prov = optional(\Laravolt\Indonesia\Models\Provinsi::where('code', $addr['provinsi'] ?? null)->first())->name ?? '-';
                    $kab  = optional(\Laravolt\Indonesia\Models\Kabupaten::where('code', $addr['kota_kab'] ?? null)->first())->name ?? '-';
                    $kec  = optional(\Laravolt\Indonesia\Models\Kecamatan::where('code', $addr['kecamatan'] ?? null)->first())->name ?? '-';
                    $kel  = optional(\Laravolt\Indonesia\Models\Kelurahan::where('code', $addr['kelurahan'] ?? null)->first())->name ?? '-';
                    return implode(', ', [
                        $addr['detail_alamat'] ?? '-',
                        $kel, $kec, $kab, $prov,
                        $addr['kode_pos'] ?? '-',
                    ]);
                })->implode("\n");
            }

            $rows[] = [
                $no++,
                $cust->id,
                $this->dashIfEmpty($cust->department->name ?? '-'),
                $this->dashIfEmpty($cust->employee->name ?? '-'),
                $this->dashIfEmpty($cust->name),
                $this->dashIfEmpty($cust->customerCategory->name ?? '-'),
                $this->dashIfEmpty($cust->phone),
                $this->dashIfEmpty($cust->email),
                $this->dashIfEmpty($fullAddress),
                $this->dashIfEmpty($cust->gmaps_link),
                $this->dashIfEmpty($cust->customerProgram->name ?? '-'),
                (int) ($cust->program_point_sum ?? 0),
                (int) ($cust->reward_point_sum ?? 0),
                '', // gambar di AfterSheet
                ucfirst($cust->status_pengajuan ?? '-'),
                ucfirst($cust->status ?? '-'),
                optional($cust->created_at)->format('Y-m-d H:i'),
                optional($cust->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:R1');
        $sheet->setCellValue('A1', 'DATA CUSTOMER');
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A2:R2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $lastRow = $sheet->getHighestRow();
        foreach (range(3, $lastRow) as $row) {
            foreach (range('A', 'R') as $col) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
            }
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(8);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(22);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(24);
        $sheet->getColumnDimension('I')->setWidth(50);
        $sheet->getColumnDimension('J')->setWidth(26);
        $sheet->getColumnDimension('K')->setWidth(22);
        $sheet->getColumnDimension('L')->setWidth(16);
        $sheet->getColumnDimension('M')->setWidth(16);
        $sheet->getColumnDimension('N')->setWidth($this->photoColumnWidthExcel); // gambar
        $sheet->getColumnDimension('O')->setWidth(18);
        $sheet->getColumnDimension('P')->setWidth(12);
        $sheet->getColumnDimension('Q')->setWidth(20);
        $sheet->getColumnDimension('R')->setWidth(20);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = 3;
                $colWidthPx = $this->photoColumnWidthExcel * $this->pxPerExcelUnit;

                foreach ($this->customersForDrawing as $cust) {
                    $imgRel = $this->firstImagePath($cust->image);

                    if ($imgRel) {
                        $absPath = null;

                        if (Storage::exists($imgRel)) {
                            $absPath = Storage::path($imgRel);
                        } elseif (Storage::disk('public')->exists($imgRel)) {
                            $absPath = Storage::disk('public')->path($imgRel);
                        }

                        if ($absPath && is_file($absPath)) {
                            [$w, $h] = @getimagesize($absPath) ?: [0, 0];
                            if ($w > 0 && $h > 0) {
                                $maxW = max(1, $colWidthPx - $this->photoPaddingPx);
                                $maxH = $this->maxPhotoHeightPx;
                                $scale = min($maxW / $w, $maxH / $h, 1);
                                $newW = $w * $scale;
                                $newH = $h * $scale;

                                $rowHeightPx = (int) max($newH + 8, 20);
                                $sheet->getRowDimension($row)->setRowHeight($rowHeightPx);

                                $offsetX = (int) max(0, ($colWidthPx - $newW) / 2);
                                $offsetY = (int) max(0, ($rowHeightPx - $newH) / 2);

                                $drawing = new Drawing();
                                $drawing->setPath($absPath);
                                $drawing->setWidth($newW);
                                $drawing->setHeight($newH);
                                $drawing->setCoordinates("N{$row}"); // kolom gambar = N
                                $drawing->setOffsetX($offsetX);
                                $drawing->setOffsetY($offsetY);
                                $drawing->setWorksheet($sheet);
                            } else {
                                $sheet->setCellValue("N{$row}", '-');
                            }
                        } else {
                            $sheet->setCellValue("N{$row}", '-');
                        }
                    } else {
                        $sheet->setCellValue("N{$row}", '-');
                    }

                    $row++;
                }

                $sheet->freezePane('A3');
            },
        ];
    }
}
