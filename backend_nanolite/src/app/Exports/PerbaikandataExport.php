<?php

namespace App\Exports;

use App\Models\Perbaikandata;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PerbaikandataExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Ambil data yang akan diexport
     */
    public function collection()
    {
        return Perbaikandata::with(['department', 'employee', 'customer', 'customerCategory'])
            ->latest()
            ->get();
    }

    /**
     * Tentukan heading kolom Excel
     */
    public function headings(): array
    {
        return [
            'ID',
            'Department',
            'Employee',
            'Customer',
            'Kategori Customer',
            'Pilihan Data',
            'Data Baru',
            'Status Pengajuan',
            'Tanggal Dibuat',
        ];
    }

    /**
     * Mapping data tiap baris
     */
    public function map($data): array
    {
        return [
            $data->id,
            $data->company->name ?? '-',
            $data->department->name ?? '-',
            $data->employee->name ?? '-',
            $data->customer->name ?? '-',
            $data->customerCategory->name ?? '-',
            $data->pilihan_data ?? '-',
            $data->data_baru ?? '-',
            $data->status_pengajuan ?? '-',
            optional($data->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}