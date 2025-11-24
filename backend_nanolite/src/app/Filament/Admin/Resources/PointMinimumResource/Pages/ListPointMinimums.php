<?php

namespace App\Filament\Admin\Resources\PointMinimumResource\Pages;

use App\Filament\Admin\Resources\PointMinimumResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPointMinimums extends ListRecords
{
    protected static string $resource = PointMinimumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
