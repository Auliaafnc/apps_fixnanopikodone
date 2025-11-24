<?php
namespace App\Filament\Admin\Resources\EmployeeResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Admin\Resources\EmployeeResource;
use Illuminate\Routing\Router;


class EmployeeApiService extends ApiService
{
    protected static string | null $resource = EmployeeResource::class;

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
