<?php
namespace App\Filament\Admin\Resources\PointMinimumResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\PointMinimumResource;
use Illuminate\Routing\Router;


class PointMinimumApiService extends ApiService
{
    protected static string | null $resource = PointMinimumResource::class;

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
