<?php

namespace App\Filament\Admin\Resources\PerbaikandataResource\Pages;

use App\Filament\Admin\Resources\PerbaikandataResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerbaikandatas extends ListRecords
{
    protected static string $resource = PerbaikandataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
