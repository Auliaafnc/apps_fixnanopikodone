<?php

namespace App\Filament\Admin\Resources\PerbaikandataResource\Pages;

use App\Filament\Admin\Resources\PerbaikandataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPerbaikandata extends EditRecord
{
    protected static string $resource = PerbaikandataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
