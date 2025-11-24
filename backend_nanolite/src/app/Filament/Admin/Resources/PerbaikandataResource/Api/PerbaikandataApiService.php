<?php
namespace App\Filament\Admin\Resources\PerbaikandataResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\PerbaikandataResource;
use Illuminate\Routing\Router;


class PerbaikandataApiService extends ApiService
{
    protected static string | null $resource = PerbaikandataResource::class;

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
