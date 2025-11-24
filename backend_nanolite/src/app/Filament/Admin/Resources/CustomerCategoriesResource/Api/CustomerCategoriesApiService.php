<?php
namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\CustomerCategoriesResource;
use Illuminate\Routing\Router;


class CustomerCategoriesApiService extends ApiService
{
    protected static string | null $resource = CustomerCategoriesResource::class;

    public static function handlers() : array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class
        ];

    }
}
