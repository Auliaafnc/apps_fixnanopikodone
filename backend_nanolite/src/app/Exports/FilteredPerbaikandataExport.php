<?php

namespace App\Exports;

use App\Models\Perbaikandata;
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

class FilteredPerbaikandataExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;

    /** simpan data utk penyisipan gambar di AfterSheet */
    protected $itemsForDrawing;

    /** preferensi gambar */
    protected int $photoColumnWidthExcel = 16; // kolom M
    protected int $pxPerExcelUnit       = 7;   // ~7px per unit excel
    protected int $photoPaddingPx       = 10;
    protected int $maxPhotoHeightPx     = 90;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    protected function dash($v): string
    {
        return (is_null($v) || trim((string)$v) === '') ? '-' : (string)$v;
    }

    /** ambil path lokal gambar pertama (untuk Drawing) dari field image (array/JSON/string) */
    protected function firstLocalImagePath($val): ?string
    {
        if (blank($val)) return null;

        // JSON string?
        if (is_string($val) && str_starts_with($val, '[')) {
            $decoded = json_decode($val, true);
            $val = is_array($decoded) ? ($decoded[0] ?? null) : $val;
        }

        // array?
        if (is_array($val)) {
            $val = $val[0] ?? null;
        }

        if (blank($val)) return null;

        // hapus prefix storage/
        $rel = preg_replace('#^/?storage/#', '', $val);

        // URL remote tidak bisa untuk Drawing
        if (preg_match('#^https?://#', $rel)) {
            return null;
        }

        $rel = ltrim($rel, '/');

        // resolve di disk default atau public
        if (Storage::exists($rel)) {
            return Storage::path($rel);
        }
        if (Storage::disk('public')->exists($rel)) {
            return Storage::disk('public')->path($rel);
        }

        return null;
    }

    public function array(): array
    {
        $q = Perbaikandata::with(['department', 'employee', 'customerCategory', 'customer']);

        // ⬇️ filter (hanya jika TIDAK export semua)
        if (empty($this->filters['export_all'])) {
            if (!empty($this->filters['department_id']))          $q->where('department_id', $this->filters['department_id']);
            if (!empty($this->filters['employee_id']))            $q->where('employee_id', $this->filters['employee_id']);
            if (!empty($this->filters['customer_id']))            $q->where('customer_id', $this->filters['customer_id']);
            if (!empty($this->filters['customer_categories_id'])) $q->where('customer_categories_id', $this->filters['customer_categories_id']);
            if (!empty($this->filters['status_pengajuan']))       $q->where('status_pengajuan', $this->filters['status_pengajuan']);
        }

        // ✅ urutkan dari yang masuk duluan (created_at ASC)
        $items = $q->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

        $this->itemsForDrawing = $items;

        // Header & judul (13 kolom: A..M, gambar di kolom M)
        $rows = [
            ['', '', '', '', '', '', 'PERBAIKAN DATA CUSTOMER', '', '', '', '', '', ''],
            [
                'No.',
                'ID',
                'Department',
                'Karyawan',
                'Kategori Customer',
                'Customer',
                'Pilihan Data',
                'Data Baru',
                'Alamat',
                'Gambar',
                'Status Pengajuan',
                'Diajukan',
                'Diupdate',
                
            ],
        ];

        $no = 1;
        foreach ($items as $r) {
            // Alamat: gunakan accessor jika ada
            $alamat = $r->full_address ?? null;
            if (!$alamat) {
                $val = $r->address ?? null;
                if (is_string($val) && str_starts_with($val, '[')) {
                    $decoded = json_decode($val, true);
                    $alamat  = is_array($decoded) ? implode(', ', array_filter($decoded)) : (string)$val;
                } elseif (is_array($val)) {
                    $alamat = implode(', ', array_filter($val));
                } else {
                    $alamat = (string) $val;
                }
            }

            $rows[] = [
                $no++,
                $r->id,
                $this->dash(optional($r->department)->name),
                $this->dash(optional($r->employee)->name),
                $this->dash(optional($r->customerCategory)->name),
                $this->dash(optional($r->customer)->name),
                $this->dash($r->pilihan_data),
                $this->dash($r->data_baru),
                $this->dash($alamat),
                '', // gambar disisipkan AfterSheet (kolom M)
                match ($r->status_pengajuan) {
                    'pending'  => 'Pending',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default    => ucfirst((string) $r->status_pengajuan),
                },
                optional($r->created_at)?->format('Y-m-d H:i'),
                optional($r->updated_at)?->format('Y-m-d H:i'),
                
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Judul (merge & center)
        $sheet->mergeCells('A1:M1');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->setCellValue('A1', 'PERBAIKAN DATA CUSTOMER');
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header
        $sheet->getStyle('A2:M2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F0F0'],
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Body
        $highestRow = $sheet->getHighestRow();
        foreach (range(3, $highestRow) as $row) {
            foreach (range('A', 'M') as $col) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_TOP,
                        'wrapText'   => true,
                    ],
                ]);
            }
            // alamat (kolom I) lebih enak rata kiri
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(6);                         // No
        $sheet->getColumnDimension('B')->setWidth(8);                         // ID
        $sheet->getColumnDimension('C')->setWidth(18);                        // Department
        $sheet->getColumnDimension('D')->setWidth(18);                        // Karyawan
        $sheet->getColumnDimension('E')->setWidth(22);                        // Kategori
        $sheet->getColumnDimension('F')->setWidth(22);                        // Customer
        $sheet->getColumnDimension('G')->setWidth(20);                        // Pilihan Data
        $sheet->getColumnDimension('H')->setWidth(30);                        // Data Baru
        $sheet->getColumnDimension('I')->setWidth(50);                        // Alamat
        $sheet->getColumnDimension('J')->setWidth($this->photoColumnWidthExcel);                      // Status Pengajuan
        $sheet->getColumnDimension('K')->setWidth(20);                        // Diajukan
        $sheet->getColumnDimension('L')->setWidth(20);                        // Diupdate
        $sheet->getColumnDimension('M')->setWidth(20); 
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Freeze header
                $sheet->freezePane('A3');

                if (empty($this->itemsForDrawing) || $this->itemsForDrawing->isEmpty()) {
                    return;
                }

                $row        = 3; // data mulai baris 3
                $colWidthPx = $this->photoColumnWidthExcel * $this->pxPerExcelUnit;

                foreach ($this->itemsForDrawing as $r) {
                    // ambil gambar pertama (jika ada)
                    $absPath = $this->firstLocalImagePath($r->image);

                    if ($absPath && is_file($absPath)) {
                        [$w, $h] = @getimagesize($absPath) ?: [0, 0];

                        if ($w > 0 && $h > 0) {
                            // skala agar muat kolom & tinggi maksimal
                            $maxW  = max(1, $colWidthPx - $this->photoPaddingPx);
                            $maxH  = $this->maxPhotoHeightPx;
                            $scale = min($maxW / $w, $maxH / $h, 1);
                            $newW  = $w * $scale;
                            $newH  = $h * $scale;

                            // set tinggi baris agar gambar pas
                            $rowHeightPx = (int) max($newH + 8, 20);
                            $sheet->getRowDimension($row)->setRowHeight($rowHeightPx);

                            // center di dalam sel M{row}
                            $offsetX = (int) max(0, ($colWidthPx - $newW) / 2);
                            $offsetY = (int) max(0, ($rowHeightPx - $newH) / 2);

                            $drawing = new Drawing();
                            $drawing->setPath($absPath);
                            $drawing->setWidth($newW);
                            $drawing->setHeight($newH);
                            $drawing->setCoordinates("M{$row}");
                            $drawing->setOffsetX($offsetX);
                            $drawing->setOffsetY($offsetY);
                            $drawing->setWorksheet($sheet);
                        } else {
                            $sheet->setCellValue("M{$row}", '-');
                        }
                    } else {
                        $sheet->setCellValue("M{$row}", '-');
                    }

                    $row++;
                }
            },
        ];
    }
}