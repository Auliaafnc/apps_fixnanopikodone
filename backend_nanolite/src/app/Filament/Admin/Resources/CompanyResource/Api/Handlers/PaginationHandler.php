<?php

namespace App\Filament\Admin\Resources\CompanyResource\Api\Handlers;

use App\Filament\Admin\Resources\CompanyResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\CompanyResource\Api\Transformers\CompanyTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = CompanyResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name','email','phone','status'])
            ->with([
                'departemen','employees','brands','categories','products',
                'customerCategories','customerPrograms','customers','orders',
                'productReturns','garansis',
            ])
            ->withCount([
                'departemen','employees','brands','categories','products',
                'customerCategories','customerPrograms','customers','orders',
                'productReturns','garansis',
            ])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($company) => new CompanyTransformer($company));

        return static::sendSuccessResponse($paginator, 'Company list retrieved successfully');
    }
}
