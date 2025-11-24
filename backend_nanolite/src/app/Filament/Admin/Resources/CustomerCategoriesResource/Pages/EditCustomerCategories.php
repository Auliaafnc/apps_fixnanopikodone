<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Pages;

use App\Filament\Admin\Resources\CustomerCategoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerCategories extends EditRecord
{
    protected static string $resource = CustomerCategoriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
