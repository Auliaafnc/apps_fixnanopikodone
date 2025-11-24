<?php

namespace App\Filament\Admin\Resources\CustomerResource\Pages;

use App\Filament\Admin\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Mutasi data sebelum disimpan
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['sales','head_sales','head_digital'])) {
            $data['department_id'] = optional($user->employee)->department_id;
            $data['employee_id']   = $user->employee_id;
        }

        return $data;
    }
}
