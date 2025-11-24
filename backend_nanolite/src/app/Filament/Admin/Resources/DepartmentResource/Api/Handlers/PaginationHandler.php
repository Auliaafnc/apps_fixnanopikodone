<?php

namespace App\Filament\Admin\Resources\DepartmentResource\Api\Handlers;

use App\Filament\Admin\Resources\DepartmentResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\DepartmentResource\Api\Transformers\DepartmentTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = DepartmentResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name','status'])
            ->with(['company','employees'])
            ->withCount(['employees'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($dept) => new DepartmentTransformer($dept));

        return static::sendSuccessResponse($paginator, 'Department list retrieved successfully');
    }
}
