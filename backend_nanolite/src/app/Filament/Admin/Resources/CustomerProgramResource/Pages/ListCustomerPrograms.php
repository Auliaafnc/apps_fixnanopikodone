<?php

namespace App\Filament\Admin\Resources\CustomerProgramResource\Pages;

use App\Filament\Admin\Resources\CustomerProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPrograms extends ListRecords
{
    protected static string $resource = CustomerProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
