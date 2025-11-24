<?php

namespace App\Exports;

use App\Models\PointMinimum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PointMinimumExport implements FromArray, WithStyles, WithEvents
{
    protected array $filters;
    protected string $title;

    public function __construct(array $filters = [], string $title = 'Minimum Poin')
    {
        $this->filters = $filters;
        $this->title   = $title;
    }

    protected function dashIfEmpty($value): string
    {
        return (is_null($value) || trim((string) $value) === '') ? '—' : (string) $value;
    }

    public function array(): array
    {
        $query = PointMinimum::with('program');

        // Filters (opsional)
        if (!empty($this->filters['type'])) {
            $query->where('type', $this->filters['type']);
        }
        if (array_key_exists('is_active', $this->filters) && $this->filters['is_active'] !== '') {
            $query->where('is_active', (bool) $this->filters['is_active']);
        }

        // ✅ Urutkan dari yang paling lama masuk ke yang paling baru
        $items = $query
            ->orderBy('created_at', 'asc')  // masuk duluan dulu
            ->orderBy('id', 'asc')          // tie-breaker kalau created_at sama
            ->get();

        // Baris 1 (judul) akan diisi di styles()
        $rows = [
            ['', '', '', '', '', '', '', ''], // A1..H1
            [
                'No.',
                'ID',
                'Tipe',
                'Program',
                'Minimum (Rp)',
                'Aktif',
                'Created At',
                'Updated At',
            ],
        ];

        $no = 1;
        foreach ($items as $item) {
            $rows[] = [
                $no++,
                $item->id,
                $item->type === 'reward' ? 'Reward' : 'Program',
                $this->dashIfEmpty(optional($item->program)->name),
                'Rp ' . number_format((float) $item->min_amount, 0, ',', '.'),
                $item->is_active ? 'Aktif' : 'Nonaktif',
                optional($item->created_at)->format('Y-m-d H:i'),
                optional($item->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Judul (baris 1)
        $sheet->mergeCells('A1:H1');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->setCellValue('A1', $this->title);
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header (baris 2)
        $sheet->getStyle('A2:H2')->applyFromArray([
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
            foreach (range('A', 'H') as $col) {
                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);
            }
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(8);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze header agar saat scroll judul & header tetap terlihat
                $event->sheet->getDelegate()->freezePane('A3');
            },
        ];
    }
}