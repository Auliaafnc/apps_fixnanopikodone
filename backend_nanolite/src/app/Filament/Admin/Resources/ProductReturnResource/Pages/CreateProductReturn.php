<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Pages;

use App\Filament\Admin\Resources\ProductReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductReturn extends CreateRecord
{
    protected static string $resource = ProductReturnResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // default status (lama & baru)
        $data['status']           = $data['status']           ?? 'pending';
        $data['status_pengajuan'] = $data['status_pengajuan'] ?? 'pending';
        $data['status_product']   = $data['status_product']   ?? 'pending';
        $data['status_return']    = $data['status_return']    ?? 'pending';

        // kunci dept & employee untuk role tertentu
        if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
            $data['department_id'] = optional($user->employee)->department_id;
            $data['employee_id']   = $user->employee_id;
        }

        // jika upload bukti delivered saat create
        if (!empty($data['delivery_images'])) {
            $data['delivered_at'] = $data['delivered_at'] ?? now();
            $data['delivered_by'] = $data['delivered_by'] ?? $user->employee_id;
            $data['status_return'] = $data['status_return'] ?? 'delivered';
        }

        return $data;
    }
}
