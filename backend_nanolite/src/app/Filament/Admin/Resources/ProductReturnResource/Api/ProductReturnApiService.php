<?php
namespace App\Filament\Admin\Resources\ProductReturnResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\ProductReturnResource;
use Illuminate\Routing\Router;


class ProductReturnApiService extends ApiService
{
    protected static string | null $resource = ProductReturnResource::class;

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
