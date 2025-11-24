<?php

namespace App\Filament\Admin\Resources\ProductResource\Api\Handlers;

use App\Filament\Admin\Resources\ProductResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\ProductResource\Api\Transformers\ProductTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = ProductResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name','description'])
            ->with(['company','brand','category'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($product) => new ProductTransformer($product));

        return static::sendSuccessResponse($paginator, 'Product list retrieved successfully');
    }
}
