<?php

namespace App\Filament\Admin\Resources\PointMinimumResource\Pages;

use App\Filament\Admin\Resources\PointMinimumResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPointMinimum extends EditRecord
{
    protected static string $resource = PointMinimumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
