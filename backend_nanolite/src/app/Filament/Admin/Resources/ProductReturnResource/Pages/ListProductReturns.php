<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Pages;

use App\Filament\Admin\Resources\ProductReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductReturns extends ListRecords
{
    protected static string $resource = ProductReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
