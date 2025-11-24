<?php
namespace App\Filament\Admin\Resources\OrderResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\OrderResource;
use Illuminate\Routing\Router;


class OrderApiService extends ApiService
{
    protected static string | null $resource = OrderResource::class;

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
