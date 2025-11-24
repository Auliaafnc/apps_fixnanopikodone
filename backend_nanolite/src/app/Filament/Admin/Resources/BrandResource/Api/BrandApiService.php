<?php
namespace App\Filament\Admin\Resources\BrandResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\BrandResource;
use Illuminate\Routing\Router;


class BrandApiService extends ApiService
{
    protected static string | null $resource = BrandResource::class;

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
