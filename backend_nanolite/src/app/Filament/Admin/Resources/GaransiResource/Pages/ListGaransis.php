<?php

namespace App\Filament\Admin\Resources\GaransiResource\Pages;

use App\Filament\Admin\Resources\GaransiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGaransis extends ListRecords
{
    protected static string $resource = GaransiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
