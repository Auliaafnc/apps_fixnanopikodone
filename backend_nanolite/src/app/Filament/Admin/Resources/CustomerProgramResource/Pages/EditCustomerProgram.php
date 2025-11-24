<?php

namespace App\Filament\Admin\Resources\CustomerProgramResource\Pages;

use App\Filament\Admin\Resources\CustomerProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerProgram extends EditRecord
{
    protected static string $resource = CustomerProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
