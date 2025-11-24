<?php
namespace App\Filament\Admin\Resources\CustomerProgramResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\CustomerProgramResource;
use Illuminate\Routing\Router;


class CustomerProgramApiService extends ApiService
{
    protected static string | null $resource = CustomerProgramResource::class;

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
