<?php
namespace App\Filament\Admin\Resources\GaransiResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\GaransiResource;
use Illuminate\Routing\Router;


class GaransiApiService extends ApiService
{
    protected static string | null $resource = GaransiResource::class;

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
