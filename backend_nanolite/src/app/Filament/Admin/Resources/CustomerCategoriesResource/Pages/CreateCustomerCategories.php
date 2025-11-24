<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Pages;

use App\Filament\Admin\Resources\CustomerCategoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerCategories extends CreateRecord
{
    protected static string $resource = CustomerCategoriesResource::class;
}
