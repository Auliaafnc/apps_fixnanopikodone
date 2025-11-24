<?php

namespace App\Filament\Admin\Resources\EmployeeResource\Api\Handlers;

use App\Filament\Admin\Resources\EmployeeResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\EmployeeResource\Api\Transformers\EmployeeTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = EmployeeResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name','email','phone','status'])
            ->with(['company','department','orders','productReturns','garansis','customers'])
            ->withCount(['orders','productReturns','garansis','customers'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($emp) => new EmployeeTransformer($emp));

        return static::sendSuccessResponse($paginator, 'Employee list retrieved successfully');
    }
}
