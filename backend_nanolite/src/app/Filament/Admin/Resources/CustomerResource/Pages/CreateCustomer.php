<?php

namespace App\Filament\Admin\Resources\CustomerResource\Pages;

use App\Filament\Admin\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // status default
        $data['status'] = 'pending';

        // batasi untuk role tertentu
        if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
            $data['department_id'] = optional($user->employee)->department_id;
            $data['employee_id']   = $user->employee_id;
        }

        return $data;
    }
}
